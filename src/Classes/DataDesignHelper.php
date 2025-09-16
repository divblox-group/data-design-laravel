<?php

namespace Divblox\Classes;

use Illuminate\Support\Facades\File;
use Closure;

abstract class DataDesignHelper {
    protected bool $UseModelForReferenceBool = false;
    protected bool $AddTimestampsToMigrationsBool = false;
    protected string $MigrationNameStr;
    protected string $MigrationFilePathStr;
    protected array $MigrationDefinitionArr = [];

    protected const FUNCTION_UP = "up";
    protected const FUNCTION_DOWN = "down";
    protected const MYSQL_DATA_TYPE_MAP = [
        'CHAR' => [
            'function' => 'char',
            'parameters' => [
                'column' => null,
                'length' => 'lengthOrValues',
            ],
        ],
        'VARCHAR' => [
            'function' => 'string',
            'parameters' => [
                'column' => null,
                'length' => 'lengthOrValues',
            ],
        ],
        'TEXT' => [
            'function' => 'text',
            'parameters' => [
                'column' => null,
            ],
        ],
        'MEDIUMTEXT' => [
            'function' => 'mediumText',
            'parameters' => [
                'column' => null,
            ],
        ],
        'LONGTEXT' => [
            'function' => 'longText',
            'parameters' => [
                'column' => null,
            ],
        ],
        'ENUM' => [
            'function' => 'enum',
            'parameters' => [
                'column' => null,
                'allowed' => 'lengthOrValues',
            ],
        ],
        'SET' => [
            'function' => 'set',
            'parameters' => [
                'column' => null,
                'allowed' => 'lengthOrValues',
            ],
        ],
        'INT' => [
            'function' => 'integer',
            'parameters' => [
                'column' => null,
            ],
        ],
        'TINYINT' => [
            'function' => 'tinyInteger',
            'parameters' => [
                'column' => null,
            ],
        ],
        'SMALLINT' => [
            'function' => 'smallInteger',
            'parameters' => [
                'column' => null,
            ],
        ],
        'MEDIUMINT' => [
            'function' => 'mediumInteger',
            'parameters' => [
                'column' => null,
            ],
        ],
        'BIGINT' => [
            'function' => 'bigInteger',
            'parameters' => [
                'column' => null,
            ],
        ],
        'DECIMAL' => [
            'function' => 'decimal',
            'parameters' => [
                'column' => null,
                'total' => null,
                'places' => null,
            ],
        ],
        'FLOAT' => [
            'function' => 'float',
            'parameters' => [
                'column' => null,
                'precision' => null,
            ],
        ],
        'DOUBLE' => [
            'function' => 'double',
            'parameters' => [
                'column' => null,
            ],
        ],
        'DATE' => [
            'function' => 'date',
            'parameters' => [
                'column' => null,
            ],
        ],
        'DATETIME' => [
            'function' => 'dateTime',
            'parameters' => [
                'column' => null,
                'precision' => null,
            ],
        ],
        'TIME' => [
            'function' => 'time',
            'parameters' => [
                'column' => null,
                'precision' => null,
            ],
        ],
        'TIMESTAMP' => [
            'function' => 'timestamp',
            'parameters' => [
                'column' => null,
                'precision' => null,
            ],
        ],
        'JSON' => [
            'function' => 'json',
            'parameters' => [
                'column' => null,
            ],
        ],
        'BOOLEAN' => [
            'function' => 'boolean',
            'parameters' => [
                'column' => null,
            ],
        ],
    ];

    protected function checkMigrationFile(): bool {
        if (empty($this->MigrationFilePathStr)) {
            return false;
        }
        if (!File::exists($this->MigrationFilePathStr)) {
            return false;
        }
        return true;
    }
    public function checkModelFile(string $PascalTableNameStr): bool {
        return File::exists(app_path("Models/{$PascalTableNameStr}.php"));
    }
    protected function getMigrationFileContent(): string|false {
        if (!$this->checkMigrationFile()) {
            return false;
        }

        $MigrationFileContentStr = file_get_contents($this->MigrationFilePathStr);
        if (empty($MigrationFileContentStr)) {
            return false;
        }
        return $MigrationFileContentStr;
    }
    public function setMigrationName(string $MigrationNameStr): void {
        $this->MigrationNameStr = $MigrationNameStr;
    }
    public function setMigrationFilePath(string $MigrationFilePathStr): void {
        $this->MigrationFilePathStr = $MigrationFilePathStr;
    }
    public function setMigrationDefinition(array $MigrationDefinitionArr): void {
        $this->MigrationDefinitionArr = $MigrationDefinitionArr;
    }
    public function setUseModelForReference(bool $UseModelForReferenceBool): void {
        $this->UseModelForReferenceBool = $UseModelForReferenceBool;
    }
    public function setAddTimestampsToMigrations(bool $AddTimestampsToMigrationsBool): void {
        $this->AddTimestampsToMigrationsBool = $AddTimestampsToMigrationsBool;
    }

    protected function setMigrationFunctionContent(string $FunctionNameStr, string $FunctionContentStr): bool {
        $MigrationFileContentStr = $this->getMigrationFileContent();
        if (empty($MigrationFileContentStr)) {
            return false;
        }

        $FunctionNameLowerStr = strtolower($FunctionNameStr);
        $MigrationFileContentStr = str_replace("public function {$FunctionNameLowerStr}(): void { }", <<<PHP
        public function {$FunctionNameLowerStr}(): void {
            {$FunctionContentStr}
            }
        PHP, $MigrationFileContentStr);
        return file_put_contents($this->MigrationFilePathStr, $MigrationFileContentStr) !== false;
    }

    public abstract function setUpFunction(): bool;
    public abstract function setDownFunction(): bool;

    protected function dataTypeFunctionTransformer(array $ColumnDefinitionArr, string $FunctionParameterValueMix): mixed {
        return match (strtolower($ColumnDefinitionArr["type"])) {
            "set", "enum" => explode(",", str_replace(['"', "'"], "", $ColumnDefinitionArr[$FunctionParameterValueMix])),
            default => $ColumnDefinitionArr[$FunctionParameterValueMix]
        };
    }
    public function buildNamedParameterString(array $NamedParametersArr): string {
        $FormattedNamedParametersArr = [];
        foreach ($NamedParametersArr as $ParameterValueMix) {
            if (is_string($ParameterValueMix)) {
                if (is_numeric($ParameterValueMix)) {
                    $ParameterValueMix = +$ParameterValueMix;
                } else {
                    $ParameterValueMix = "'{$ParameterValueMix}'";
                }
                $FormattedNamedParametersArr[] = $ParameterValueMix;
            } elseif (is_null($ParameterValueMix)) {
                $FormattedNamedParametersArr[] = 'null';
            } elseif (is_int($ParameterValueMix) || is_float($ParameterValueMix)) {
                $FormattedNamedParametersArr[] = $ParameterValueMix;
            } elseif (is_bool($ParameterValueMix)) {
                $FormattedNamedParametersArr[] = $ParameterValueMix ? 'true' : 'false';
            } elseif (is_array($ParameterValueMix)) {
                $arrayContent = $this->convertArrayToString($ParameterValueMix);
                $FormattedNamedParametersArr[] = $arrayContent;
            }
        }
        return implode(', ', $FormattedNamedParametersArr);
    }
    protected function convertArrayToString(array $ParameterValueMix): string {
        $ArrayElementsArr = [];
        $IsAssocArrayBool = array_keys($ParameterValueMix) !== range(0, count($ParameterValueMix) - 1);
        foreach ($ParameterValueMix as $ArrayParameterKey => $ArrayParameterValueMix) {
            $ArrayElementMix = '';
            if ($IsAssocArrayBool) {
                $ArrayElementMix .= is_string($ArrayParameterKey) ? "'$ArrayParameterKey' => " : "$ArrayParameterKey => ";
            }

            if (is_string($ArrayParameterValueMix)) {
                if (is_numeric($ArrayParameterValueMix)) {
                    $ArrayParameterValueMix = +$ArrayParameterValueMix;
                } else {
                    $ArrayParameterValueMix = "'{$ArrayParameterValueMix}'";
                }
                $ArrayElementMix .= $ArrayParameterValueMix;
            } elseif (is_null($ArrayParameterValueMix)) {
                $ArrayElementMix .= 'null';
            } elseif (is_int($ArrayParameterValueMix) || is_float($ArrayParameterValueMix)) {
                $ArrayElementMix .= $ArrayParameterValueMix;
            } elseif (is_bool($ArrayParameterValueMix)) {
                $ArrayElementMix .= $ArrayParameterValueMix ? 'true' : 'false';
            } elseif (is_array($ArrayParameterValueMix)) {
                $ArrayElementMix .= $this->convertArrayToString($ArrayParameterValueMix);
            }
            $ArrayElementsArr[] = $ArrayElementMix;
        }
        return '['.implode(', ', $ArrayElementsArr).']';
    }

    protected function processTransformer(array|Closure $TransformerConfigMix, string $TransformingInputStr) {
        if (empty($TransformerConfigMix)) {
            return $TransformingInputStr;
        }
        if (is_array($TransformerConfigMix)) {
            if (empty($TransformerConfigMix["class"]) ||
                empty($TransformerConfigMix["method"])
            ) {
                return $TransformingInputStr;
            }
            return $TransformerConfigMix["class"]::{$TransformerConfigMix["method"]}($TransformingInputStr);
        }

        if ($TransformerConfigMix instanceof Closure) {
            return $TransformerConfigMix($TransformingInputStr);
        }
        return $TransformingInputStr;
    }
}
