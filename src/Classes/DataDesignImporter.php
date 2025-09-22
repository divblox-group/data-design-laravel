<?php

namespace Divblox\Classes;

use Illuminate\Support\Str;

class DataDesignImporter extends DataDesignHelper {
    public function setUpFunction(): bool {
        $ColumnDefinitionsArr = [
            "\$table->id();"
        ];

        if ($this->AddTimestampsToMigrationsBool) {
            $ColumnDefinitionsArr[] = "\$table->datetimes();";
        }

        $ColumnDefinitionsArr = array_merge(
            $ColumnDefinitionsArr,
            $this->getTableColumnDefinitions($this->MigrationDefinitionArr["attributes"])
        );
        $ColumnDefinitionsArr[] = "";
        $ColumnDefinitionsArr = array_merge(
            $ColumnDefinitionsArr,
            $this->getTableRelationshipDefinitions($this->MigrationDefinitionArr["relationships"])
        );

        $ColumnDefinitionsStr = implode("\n\t\t\t", $ColumnDefinitionsArr);
        $TableNameStr = Helper::processConfigTransformer("divblox.data_design.transformers.table", $this->MigrationNameStr);
        return $this->setMigrationFunctionContent(self::FUNCTION_UP, <<<PHP
Schema::disableForeignKeyConstraints();
        Schema::create('{$TableNameStr}', function (Blueprint \$table) {
            {$ColumnDefinitionsStr}
        });
        Schema::enableForeignKeyConstraints();
PHP);
    }
    public function setDownFunction(): bool {
        return $this->setMigrationFunctionContent(self::FUNCTION_DOWN, <<<PHP
            Schema::dropIfExists('{$this->MigrationNameStr}');
        PHP
        );
    }

    protected function getTableColumnDefinitions(array $TableAttributesArr): array {
        $ColumnDefinitionsArr = [];
        foreach ($TableAttributesArr as $ColumnNameStr => $ColumnDefinitionArr) {
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
                        $ColumnFunctionParameterArr[$FunctionParameterNameStr] = Helper::processConfigTransformer("divblox.data_design.transformers.column", $ColumnNameStr);
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
                switch (Str::upper($DataTypeFunctionStr)) {
                    case Str::upper("JSON"):
                        if (empty($ColumnDefinitionArr["default"]) ||
                            $ColumnDefinitionArr["default"] === "'{}'" ||
                            $ColumnDefinitionArr["default"] === "'[]'"
                        ) {
                            $ColumnDefinitionArr["default"] = "DB::raw('(JSON_ARRAY())')";
                        }
                    break;
                }
                $ColumnDefaultStr = "->default({$ColumnDefinitionArr["default"]})";
            }

            $ColumnNullableStr = $ColumnDefinitionArr["allowNull"] ? "->nullable()" : "";


            $ColumnDefinitionsArr[] = <<<PHP
\$table->{$DataTypeFunctionStr}({$this->buildNamedParameterString($ColumnFunctionParameterArr)}){$ColumnIndexMix}{$ColumnDefaultStr}{$ColumnNullableStr};
PHP;
        }
        return $ColumnDefinitionsArr;
    }
    protected function getTableRelationshipDefinitions(array $TableRelationshipsArr): array {
        $ColumnDefinitionsArr = [];
        foreach ($TableRelationshipsArr as $ReferenceTableNameStr => $ForeignKeyNamesArr) {
            //            $PascalTableNameStr = Str::pascal($ReferenceTableNameStr);
            $PluralSnakeTableNameStr = Str::snake(Str::plural($ReferenceTableNameStr));
            $SingularSnakeTableNameStr = Str::snake(Str::singular($ReferenceTableNameStr));
            foreach ($ForeignKeyNamesArr as $ForeignKeyNameStr) {
                $ColumnDefinitionsArr[] = <<<PHP
\$table->foreignId('{$SingularSnakeTableNameStr}_id')->nullable()->constrained('{$PluralSnakeTableNameStr}', 'id')->onUpdate('cascade')->onDelete('cascade');
PHP;
                //                if ($this->UseModelForReferenceBool ||
                //                    $this->checkModelFile($PascalTableNameStr)
                //                ) {
                //                    $ColumnDefinitionsArr[] = <<<PHP
                //\$table->foreignIdFor(\App\Models\\$PascalTableNameStr::class)->index()->nullable();
                //PHP;
                //                } else {
                //                    $ForeignKeyNameStr = Str::snake($ForeignKeyNameStr);
                //                    $ColumnDefinitionsArr[] = <<<PHP
                //\$table->foreignId('{$ForeignKeyNameStr}')->constrained('{$PluralSnakeTableNameStr}', 'Id', '{$this->MigrationNameStr}_{$ForeignKeyNameStr}')->index()->nullable();
                //PHP;
                //                }
            }
        }
        return $ColumnDefinitionsArr;
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
