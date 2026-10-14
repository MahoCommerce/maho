<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'storage:image:resize',
    description: 'Create the missing resized files of the images, then switch the store views to the current image settings',
)]
class StorageImageResize extends BaseMahoCommand
{
    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Option(description: 'Only this image entity, such as product, category or blog')]
        ?string $entity = null,
        #[Option(description: 'The number of parallel jobs. Default: 1 on a local disk, ' . StorageMigrate::DEFAULT_REMOTE_JOBS . ' on a bucket')]
        ?int $jobs = null,
        #[Option(description: 'Resize the images that stdin lists as JSON, and print the counts as JSON. The parallel jobs use it')]
        bool $filesFromStdin = false,
    ): int {
        $this->initMaho();

        $entities = array_keys(Mage::getSingleton('core/image_config')->getEntities());
        if ($entity !== null) {
            if (!in_array($entity, $entities, true)) {
                $io->error("Unknown image entity \"{$entity}\". Known entities: " . implode(', ', $entities));
                return Command::INVALID;
            }
            $entities = [$entity];
        }

        if ($filesFromStdin) {
            return $entity === null ? Command::INVALID : $this->resizeFilesFromStdin($io, $input, $entity);
        }

        $config = Mage::getSingleton('core/image_config');
        $resizer = Mage::getSingleton('core/image_resizer');
        $jobCount = max(1, $jobs ?? (Mage::getStorage('media')->isLocal() ? 1 : StorageMigrate::DEFAULT_REMOTE_JOBS));
        $status = Command::SUCCESS;
        foreach ($entities as $name) {
            $io->section($name);
            $source = $config->getSource($name);
            $settingsList = $config->getAllSettings($name);

            $io->text('Step 1 of 2: list the resized files that exist.');
            $listing = $io->createProgressBar();
            $listing->setFormat(' %current% folders | elapsed %elapsed%');
            $listing->start();
            $existing = [];
            foreach ($settingsList as $settings) {
                $existing += $resizer->listExistingFiles($source, $settings);
                $listing->advance();
            }
            $listing->finish();
            $io->newLine(2);

            $files = $resizer->filterMissing($name, $resizer->getSourceFiles($name), $settingsList, $existing);
            $io->text('Step 2 of 2: create the missing files of ' . count($files) . ' images' . ($jobCount > 1 && count($files) > 1 ? " with {$jobCount} jobs." : '.'));
            $progress = $io->createProgressBar(count($files));
            // The time left needs a total above 0
            $progress->setFormat($files === [] ? ' %current%/%max%' : ' %current%/%max% [%bar%] %percent:3s%% | elapsed %elapsed% | left %remaining%');
            $progress->start();
            $count = 0;
            $failed = [];
            if ($jobCount === 1) {
                $count = $resizer->createFiles($name, $files, $settingsList, $existing, fn() => $progress->advance(), function (string $file) use (&$failed): void {
                    $failed[] = $file;
                });
            } else {
                $this->runJobs(
                    $progress,
                    $this->splitIntoParts($files, $jobCount),
                    ['storage:image:resize', '--entity=' . $name, '--files-from-stdin'],
                    function (array $part) use (&$count, &$failed): void {
                        $count += (int) $part['created'];
                        $failed = [...$failed, ...$part['failed']];
                    },
                    function (int $i, string $error) use (&$failed, $jobCount): void {
                        $failed[] = "(job {$i}/{$jobCount}) {$error}";
                    },
                );
            }
            $progress->finish();
            $io->newLine(2);
            $io->text("Created {$count} file(s).");

            if ($failed !== []) {
                $io->listing($failed);
                $io->error("Some files of {$name} were not created, so its store views keep their settings. Run the command again: it creates only what is missing.");
                $status = Command::FAILURE;
                continue;
            }
            $resizer->activateLiveSettings($name);
        }

        if ($status === Command::SUCCESS) {
            $io->success('The resized files exist, and every store view uses its current image settings.');
        }
        return $status;
    }

    /**
     * Create the files of the images that stdin lists as JSON. Print a line for each image, then
     * a line with the counts as JSON.
     */
    private function resizeFilesFromStdin(SymfonyStyle $io, InputInterface $input, string $entity): int
    {
        $stdin = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $files = Mage::helper('core')->jsonDecode((string) stream_get_contents($stdin ?? STDIN));
        if (!is_array($files)) {
            $io->error('--files-from-stdin needs a JSON list of image files.');
            return Command::FAILURE;
        }

        $failed = [];
        $count = Mage::getSingleton('core/image_resizer')->createFiles(
            $entity,
            array_map(strval(...), array_values($files)),
            null,
            null,
            fn() => $io->writeln(self::JOB_ITEM_LINE, OutputInterface::OUTPUT_RAW),
            function (string $file) use (&$failed): void {
                $failed[] = $file;
            },
        );
        $io->writeln(self::JOB_RESULT_PREFIX . Mage::helper('core')->jsonEncode([
            'created' => $count,
            'failed' => $failed,
        ]), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
