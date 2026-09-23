<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Driver\Sqlite;

use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

final class Statement implements StatementInterface
{
    public function __construct(private readonly \PDOStatement $statement) {}

    #[\Override]
    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $pdoType = match ($type) {
            ParameterType::NULL => \PDO::PARAM_NULL,
            ParameterType::INTEGER => \PDO::PARAM_INT,
            ParameterType::STRING, ParameterType::ASCII => \PDO::PARAM_STR,
            ParameterType::BINARY, ParameterType::LARGE_OBJECT => \PDO::PARAM_LOB,
            ParameterType::BOOLEAN => \PDO::PARAM_BOOL,
        };

        try {
            $this->statement->bindValue($param, $value, $pdoType);
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
    }

    #[\Override]
    public function execute(): Result
    {
        try {
            $this->statement->execute();
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
        return new Result($this->statement);
    }
}
