<?php

namespace App\Classes;

use Illuminate\Support\Str;

class DivbloxDataModelImportHelper extends DivbloxDataDesignHelper {
    public function setUpFunction(): bool {
        $ColumnDefinitionsArr = [
            "\$table->id();"
        ];

        if ($this->AddTimestampsToMigrationsBool) {
            $ColumnDefinitionsArr[] = "\$table->datetimes();";
        }
        foreach ($this->MigrationDefinitionArr["attributes"] as $ColumnNameStr => $ColumnDefinitionArr) {
            if (in_array(Str::upper($ColumnNameStr), [
                Str::upper("Id")
            ])) {
                 continue;
            }
            if (empty(self::MYSQL_DATA_TYPE_MAP[strtoupper($ColumnDefinitionArr["type"])])) {
                continue;
            }
            $DataTypeFunctionStr = self::MYSQL_DATA_TYPE_MAP[strtoupper($ColumnDefinitionArr["type"])]["function"];

            $ColumnFunctionParameterArr = self::MYSQL_DATA_TYPE_MAP[strtoupper($ColumnDefinitionArr["type"])]["parameters"];
            foreach ($ColumnFunctionParameterArr as $FunctionParameterNameStr => $FunctionParameterValueMix) {
                switch ($FunctionParameterNameStr) {
                    case "column":
                        $ColumnFunctionParameterArr[$FunctionParameterNameStr] = $ColumnNameStr;
                    break;
                    default:
                        if (empty($FunctionParameterValueMix)) {
                            continue 2;
                        }
                        $ColumnFunctionParameterArr[$FunctionParameterNameStr] = $this->dataTypeFunctionTransformer($ColumnDefinitionArr, $FunctionParameterValueMix);
                    break;
                }
            }

            $ColumnIndexDefinitionsArr = [];
            foreach ($this->MigrationDefinitionArr["indexes"] as $IndexDefinitionArr) {
                if (Str::upper($IndexDefinitionArr["attribute"]) !== Str::upper($ColumnNameStr)) {
                    continue;
                }
                $ColumnIndexDefinitionsArr[] = $IndexDefinitionArr;
            }

            $ColumnIndexMix = "";
            foreach ($ColumnIndexDefinitionsArr as $ColumnIndexDefinitionArr) {
                $IndexChoiceStr = Str::lower($ColumnIndexDefinitionArr["indexChoice"]);
                $ColumnIndexMix .= "->{$IndexChoiceStr}()";
            }
            $ColumnDefaultStr = "";
            if (!empty($ColumnDefinitionArr["default"])) {
                if (is_numeric($ColumnDefinitionArr["default"])) {
                    $ColumnDefinitionArr["default"] = +$ColumnDefinitionArr["default"];
                } else if (is_string($ColumnDefinitionArr["default"])) {
                    $ColumnDefinitionArr["default"] = "'{$ColumnDefinitionArr["default"]}'";
                }
                $ColumnDefaultStr = "->default({$ColumnDefinitionArr["default"]})";
            }

            $ColumnNullableStr = $ColumnDefinitionArr["allowNull"] ? "->nullable()" : "";


            $ColumnDefinitionsArr[] = <<<PHP
\$table->{$DataTypeFunctionStr}({$this->buildNamedParameterString($ColumnFunctionParameterArr)}){$ColumnIndexMix}{$ColumnDefaultStr}{$ColumnNullableStr};
PHP;
        }


        $ColumnDefinitionsArr[] = "";
        foreach ($this->MigrationDefinitionArr["relationships"] as $ReferenceTableNameStr => $ForeignKeyNamesArr) {
            $PascalTableNameStr = Str::pascal($ReferenceTableNameStr);
            $PluralSnakeTableNameStr = Str::snake(Str::plural($ReferenceTableNameStr));
            foreach ($ForeignKeyNamesArr as $ForeignKeyNameStr) {
                if ($this->UseModelForReferenceBool ||
                    $this->checkModelFile($PascalTableNameStr)
                ) {
                    $ColumnDefinitionsArr[] = <<<PHP
\$table->foreignIdFor(\App\Models\\$PascalTableNameStr::class)->index();
PHP;
                } else {
                    $ForeignKeyNameStr = Str::snake($ForeignKeyNameStr);
                    $ColumnDefinitionsArr[] = <<<PHP
\$table->foreignId('{$ForeignKeyNameStr}')->constrained('{$PluralSnakeTableNameStr}', 'Id', '{$this->MigrationNameStr}_{$ForeignKeyNameStr}')->index();
PHP;
                }
            }

        }

        $ColumnDefinitionsStr = implode("\n\t\t\t", $ColumnDefinitionsArr);
        return $this->setMigrationFunctionContent(self::FUNCTION_UP, <<<PHP
    Schema::create('{$this->MigrationNameStr}', function (Blueprint \$table) {
            {$ColumnDefinitionsStr}
        });
PHP);
    }
    public function setDownFunction(): bool {
        return $this->setMigrationFunctionContent(self::FUNCTION_DOWN, <<<PHP
            Schema::dropIfExists('{$this->MigrationNameStr}');
        PHP
        );
    }

    protected function setMigrationFunction(string $FunctionNameStr): bool {
        $MigrationFileContentStr = $this->getMigrationFileContent();
        if (empty($MigrationFileContentStr)) {
            return false;
        }

        $FunctionNameUpperStr = strtoupper($FunctionNameStr);
        $FunctionNameLowerStr = strtolower($FunctionNameStr);
        $MigrationFileContentStr = str_replace("//{$FunctionNameUpperStr}", <<<PHP
        public function {$FunctionNameLowerStr}(): void { }
        PHP, $MigrationFileContentStr);
        return file_put_contents($this->MigrationFilePathStr, $MigrationFileContentStr) !== false;
    }
    public function resetMigrationFile(bool $SetEmptyFunctionsBool = false): bool {
        $MigrationFileContentStr = $this->getMigrationFileContent();
        if (empty($MigrationFileContentStr)) {
            return false;
        }

        $NewMigrationFileContentStr = preg_replace('/return new class extends Migration\s*\{[\s\S]*?\};/', "return new class extends Migration {\n\t//UP\n\n\t//DOWN\n};", $MigrationFileContentStr);

        if (empty($NewMigrationFileContentStr) || $NewMigrationFileContentStr === $MigrationFileContentStr) {
            return false;
        }

        return file_put_contents($this->MigrationFilePathStr, $NewMigrationFileContentStr) !== false && (!$SetEmptyFunctionsBool || $this->setMigrationFunction(self::FUNCTION_UP) && $this->setMigrationFunction(self::FUNCTION_DOWN));
    }
}
