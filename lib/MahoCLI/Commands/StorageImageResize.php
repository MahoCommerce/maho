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
    description: 'Create the missing resized files of the images, switch the store views to the current image settings, and store the missing image sizes',
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
        #[Option(description: 'Store the sizes of the media files that stdin lists as JSON, and print the failures as JSON. The parallel jobs use it')]
        bool $sizesFromStdin = false,
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
        if ($sizesFromStdin) {
            return $this->readSizesFromStdin($io, $input);
        }

        $config = Mage::getSingleton('core/image_config');
        $resizer = Mage::getSingleton('core/image_resizer');
        $jobCount = max(1, $jobs ?? (Mage::getStorage('media')->isLocal() ? 1 : StorageMigrate::DEFAULT_REMOTE_JOBS));
        $status = Command::SUCCESS;
        foreach ($entities as $name) {
            $io->section($name);
            $source = $config->getSource($name);
            $settingsList = $config->getAllSettings($name);

            $io->text('Step 1 of 3: list the resized files that exist.');
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

            $sourceFiles = $resizer->getSourceFiles($name);
            $sourceFiles = is_array($sourceFiles) ? $sourceFiles : iterator_to_array($sourceFiles, false);
            $files = $resizer->filterMissing($name, $sourceFiles, $settingsList, $existing);
            $io->text('Step 2 of 3: create the missing files of ' . count($files) . ' images' . ($jobCount > 1 && count($files) > 1 ? " with {$jobCount} jobs." : '.'));
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
            } else {
                $resizer->activateLiveSettings($name);
            }

            if (!$this->readMissingSizes($io, $name, $sourceFiles, $jobCount)) {
                $status = Command::FAILURE;
            }
        }

        if ($status === Command::SUCCESS) {
            $io->success('The resized files exist, every store view uses its current image settings, and every image has a stored size.');
        }
        return $status;
    }

    /**
     * Step 3: store the size of each source file of $entity that has no row in core_image_size,
     * so a page never reads a file on a bucket for it.
     *
     * @param list<string> $sourceFiles
     * @return bool false when some sizes could not be read
     */
    private function readMissingSizes(SymfonyStyle $io, string $entity, array $sourceFiles, int $jobCount): bool
    {
        $paths = Mage::getSingleton('core/image_size')->filterUnknown($entity, $sourceFiles);
        $io->text('Step 3 of 3: store the size of ' . count($paths) . ' images that have no stored size' . ($jobCount > 1 && count($paths) > 1 ? " with {$jobCount} jobs." : '.'));
        $progress = $io->createProgressBar(count($paths));
        $progress->setFormat($paths === [] ? ' %current%/%max%' : ' %current%/%max% [%bar%] %percent:3s%% | elapsed %elapsed% | left %remaining%');
        $progress->start();
        $failed = [];
        if ($jobCount === 1) {
            $failed = $this->readSizes($paths, fn() => $progress->advance());
        } else {
            $this->runJobs(
                $progress,
                $this->splitIntoParts($paths, $jobCount),
                ['storage:image:resize', '--sizes-from-stdin'],
                function (array $part) use (&$failed): void {
                    $failed = [...$failed, ...$part['failed']];
                },
                function (int $i, string $error) use (&$failed, $jobCount): void {
                    $failed[] = "(job {$i}/{$jobCount}) {$error}";
                },
            );
        }
        $progress->finish();
        $io->newLine(2);

        if ($failed !== []) {
            $io->listing($failed);
            $io->error("Some sizes of {$entity} were not read. Run the command again: it reads only what is missing.");
            return false;
        }
        return true;
    }

    /**
     * Store the size of each of $paths on the media mount.
     *
     * @param list<string> $paths
     * @return list<string> the paths that the mount could not read, each with the error
     */
    private function readSizes(array $paths, callable $onFile): array
    {
        $failed = [];
        Mage::getSingleton('core/image_size')->readAll('media', $paths, function (string $path, \Throwable $e) use (&$failed): void {
            $failed[] = "{$path}: {$e->getMessage()}";
        }, $onFile);
        return $failed;
    }

    /**
     * Store the sizes of the media files that stdin lists as JSON. Print a line for each file,
     * then a line with the failures as JSON.
     */
    private function readSizesFromStdin(SymfonyStyle $io, InputInterface $input): int
    {
        $stdin = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $paths = Mage::helper('core')->jsonDecode((string) stream_get_contents($stdin ?? STDIN));
        if (!is_array($paths)) {
            $io->error('--sizes-from-stdin needs a JSON list of paths.');
            return Command::FAILURE;
        }

        $failed = $this->readSizes(
            array_map(strval(...), array_values($paths)),
            fn() => $io->writeln(self::JOB_ITEM_LINE, OutputInterface::OUTPUT_RAW),
        );
        $io->writeln(self::JOB_RESULT_PREFIX . Mage::helper('core')->jsonEncode(['failed' => $failed]), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
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

        $config = Mage::getSingleton('core/image_config');
        $resizer = Mage::getSingleton('core/image_resizer');
        $existing = [];
        foreach ($config->getAllSettings($entity) as $settings) {
            $existing += $resizer->listExistingFiles($config->getSource($entity), $settings);
        }

        $failed = [];
        $count = $resizer->createFiles(
            $entity,
            array_map(strval(...), array_values($files)),
            null,
            $existing,
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
