<?php

namespace Ivanbekker\DataDesignLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use function Laravel\Prompts\select;
use function Laravel\Prompts\search;
use function Laravel\Prompts\confirm;
use DirectoryIterator;
use Illuminate\Support\Facades\File;
use Throwable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;
use App\Classes\DivbloxDataModelImportHelper;
use ZipArchive;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Env;

class DataModelImporter extends Command implements PromptsForMissingInput {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'divblox:import {type}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates the migration files from the Divblox Data Design App';

    private const TYPE_FILE = "File";
    private const TYPE_GUID = "GUID";

    protected bool $RemoveZipFileAfterExtractBool = false;
    protected string $RemovingZipFilePathStr = "";

    protected bool $RemoveJsonFileAfterExtractBool = false;
    protected string $RemovingJsonFilePathStr = "";

    protected function promptForMissingArgumentsUsing(): array {
        return [
            "type" => fn() => select(label: "Please select how you want to import your Divblox Data Model", options: [
                $this::TYPE_FILE,
                $this::TYPE_GUID,
            ]),
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $this->alert("Welcome to the Divblox Importer");

        $ImportedDataModelJsonArr = match (strtolower($this->argument("type"))) {
            strtolower($this::TYPE_FILE) => $this->getFromFile(),
            strtolower($this::TYPE_GUID) => $this->getFromGuid(),
            default => $this->fail("Invalid Type passed.")
        };

        if (empty($ImportedDataModelJsonArr)) {
            $this->fail("Empty Data Model provided.");
        }

        $CreateModelFilesBool = confirm(
            "Do you want to create the Model files as well?",
            false
        );
        $AddTimestampsToMigrationsBool = confirm(
            "Do you want to add the default Laravel timestamps to the generated migrations?",
            false
        );
        $TotalEntitiesToImportInt = count(array_keys($ImportedDataModelJsonArr));
        $this->info("Importing {$TotalEntitiesToImportInt} entities");

        $ImportProgressObj = $this->output->createProgressBar($TotalEntitiesToImportInt);
        $ImportProgressObj->start();

        $MigrationModifierObj = new DivbloxDataModelImportHelper();
        foreach ($ImportedDataModelJsonArr as $EntityNameStr => $ImportedDataModelItemArr) {
            if ($EntityNameStr == "account") {
                \Log::info(print_r($ImportedDataModelItemArr, true));
            }
            if (empty($ImportedDataModelItemArr["attributes"])) {
                $this->error("Entity attributes not defined: {$EntityNameStr}");
                continue;
            }

            $PascalEntityNameStr = Str::pascal($EntityNameStr);
            if ($CreateModelFilesBool && !$MigrationModifierObj->checkModelFile($PascalEntityNameStr)) {
                Artisan::call("make:model", [
                    "name" => $PascalEntityNameStr
                ]);
            }

            $MigrationFileNameStr = \App\Console\Commands\database_path("migrations/{$this->createMigration($EntityNameStr)}");
            if (!File::exists($MigrationFileNameStr)) {
                $this->deleteMigration($MigrationFileNameStr);
                $this->error("Failed to create migration file: {$EntityNameStr}");
                continue;
            }

            $MigrationModifierObj->setMigrationName(Str::snake(Str::plural($EntityNameStr)));
            $MigrationModifierObj->setMigrationFilePath($MigrationFileNameStr);
            $MigrationModifierObj->setUseModelForReference($CreateModelFilesBool);
            $MigrationModifierObj->setAddTimestampsToMigrations($AddTimestampsToMigrationsBool);
            $MigrationModifierObj->setMigrationDefinition($ImportedDataModelItemArr);

            if (!$MigrationModifierObj->resetMigrationFile(true)) {
                $this->deleteMigration($MigrationFileNameStr);
                $this->newLine();
                $this->error("Failed to reset '{$EntityNameStr}' migration file.");
                continue;
            }
            if (!$MigrationModifierObj->setUpFunction()) {
                $this->deleteMigration($MigrationFileNameStr);
                $this->newLine();
                $this->error("Failed to set '{$EntityNameStr}' UP function in migration file.");
                continue;
            }
            if (!$MigrationModifierObj->setDownFunction()) {
                $this->deleteMigration($MigrationFileNameStr);
                $this->newLine();
                $this->error("Failed to set '{$EntityNameStr}' DOWN function in migration file.");
                continue;
            }
            $ImportProgressObj->advance();
        }

        if ($this->RemoveZipFileAfterExtractBool) {
            if (!empty($this->RemovingZipFilePathStr) &&
                File::exists($this->RemovingZipFilePathStr)
            ) {
                unlink($this->RemovingZipFilePathStr);
            }
        }
        if ($this->RemoveJsonFileAfterExtractBool) {
            if (!empty($this->RemovingJsonFilePathStr) &&
                File::exists($this->RemovingJsonFilePathStr)
            ) {
                unlink($this->RemovingJsonFilePathStr);
            }
        }
        $ImportProgressObj->finish();
        return 0;
    }

    protected function getFromFile(): array {
        $FileLocationStr = search("Please provide the location of your extracted Data Model.", function($value) {
            $ListedFilesArr = [];
            /** @var DirectoryIterator $FileObj */
            foreach (new DirectoryIterator(base_path()) as $FileObj) {
                if ($FileObj->isDot()) {
                    continue;
                }
                if ($FileObj->isDir()) {
                    continue;
                }
                if (empty($value)) {
                    $ListedFilesArr[] = $FileObj->getFilename();
                    continue;
                }
                if (!preg_match("/{$value}/", $FileObj->getFilename())) {
                    continue;
                }
                $ListedFilesArr[] = $FileObj->getFilename();
            }
            return $ListedFilesArr;
        }, base_path("data-model.json"));

        if (empty($FileLocationStr)) {
            $this->fail("Invalid file path. [1]");
        }

        if (!File::exists($FileLocationStr)) {
            $this->fail("Invalid file path. [2]");
        }

        $FileExtensionStr = File::extension($FileLocationStr);
        return match(strtolower($FileExtensionStr)) {
            strtolower("json") => $this->getFromJsonFile($FileLocationStr),
            strtolower("zip") => $this->getFromZIPFile($FileLocationStr),
            default => $this->fail("Invalid file type selected: {$FileExtensionStr}. Allowed files are [JSON, ZIP]")
        };
    }
    protected function getFromJsonFile(string $FileLocationStr): array {
        try {
            $ImportedDataModelJsonArr = json_decode(File::get($FileLocationStr), true);
        } catch (Throwable $ExceptionObj) {
            $this->fail($ExceptionObj);
        }
        $this->RemoveJsonFileAfterExtractBool = confirm(
            "Do you want to remove the json file after successful import",
            false
        );
        if ($this->RemoveJsonFileAfterExtractBool) {
            $this->RemovingJsonFilePathStr = $FileLocationStr;
        }

        return $ImportedDataModelJsonArr;
    }
    protected function getFromZIPFile(string $FileLocationStr): array {
        $ZipArchiveObj = new ZipArchive();
        if ($ZipArchiveObj->open($FileLocationStr) !== true) {
            $this->fail("Failed to extract zip file: {$FileLocationStr}");
        }

        if (empty($ZipArchiveObj->numFiles)) {
            $this->fail("Zip file was empty.");
        }


        $DataModelJsonFileNameStr = "data-model.json";
        $DataModelJsonFilePathStr = "";
        foreach (range(0, $ZipArchiveObj->numFiles) as $Index) {
            if (!str_contains($ZipArchiveObj->getNameIndex($Index), $DataModelJsonFileNameStr)) {
                continue;
            }
            $DataModelJsonFilePathStr = $ZipArchiveObj->getNameIndex($Index);
            break;
        }

        if (empty($DataModelJsonFilePathStr)) {
            $this->fail("Zip does not contain the needed file for import: '{$DataModelJsonFileNameStr}");
        }

        $ExtractedDataModelJsonFilePathStr = \App\Console\Commands\base_path($DataModelJsonFileNameStr);
        $this->info("Extracting file: {$DataModelJsonFilePathStr} from zip into {$ExtractedDataModelJsonFilePathStr}");

        if (file_put_contents($ExtractedDataModelJsonFilePathStr, $ZipArchiveObj->getFromName($DataModelJsonFilePathStr)) === false) {
            $this->fail("Failed to extract '{$DataModelJsonFileNameStr}' from the provided zip file: {$FileLocationStr}");
        }

        $this->RemoveZipFileAfterExtractBool = confirm(
            "Do you want to remove the zip file after successful import",
            false
        );
        if ($this->RemoveZipFileAfterExtractBool) {
            $this->RemovingZipFilePathStr = $FileLocationStr;
        }
        return $this->getFromJsonFile($ExtractedDataModelJsonFilePathStr);
    }

    protected function getFromGuid(): array {
        $DivbloxDataDesignApiKeyStr = $this->getDataDesignInformationNeededForRequest("API_KEY");
        $DivbloxDataDesignGuidStr = $this->getDataDesignInformationNeededForRequest("GUID");

        $this->info("Import data model for 'GUID': $DivbloxDataDesignGuidStr");

        $ResponseObj = Http::withoutVerifying()->post("https://api.divblox.app/api/dataDesign/pullProjectDataModel/{$DivbloxDataDesignGuidStr}", [
            "dxApiKey" => $DivbloxDataDesignApiKeyStr
        ]);

        $ResponseBodyArr = json_decode($ResponseObj->body(), true);
        if (!$ResponseObj->successful()) {
            $this->fail($ResponseBodyArr["message"] ?? "Failed to fetch data model");
        }
        return $ResponseBodyArr;
    }
    protected function getDataDesignInformationNeededForRequest(string $EnvVariableSuffixStr): string {
        $EnvVariableSuffixStr = Str::upper($EnvVariableSuffixStr);
        $DivbloxDataDesignVariableStr = Env::get("DX_DATA_DESIGN_{$EnvVariableSuffixStr}");
        if (empty($DivbloxDataDesignVariableStr)) {
            $DivbloxDataDesignVariableStr = $this->ask("Please provide your '{$EnvVariableSuffixStr}' required to fetch the Data Model from the Data Design App");
        }
        if (empty($DivbloxDataDesignVariableStr)) {
            $this->fail("'{$EnvVariableSuffixStr}' is required to continue with the process, or export the data model and import via 'File'");
        }
        return $DivbloxDataDesignVariableStr;
    }

    protected function createMigration(string $EntityNameStr): string {
        $BufferedOutputObj = new BufferedOutput();
        Artisan::call("make:migration", [
            "name" => "create_".Str::camel(Str::plural($EntityNameStr))
        ], $BufferedOutputObj);
        return $this->getMigrationFileNameFromBuffer($BufferedOutputObj);
    }
    protected function deleteMigration(string $MigrationFileNameStr): string {
        if (!File::exists($MigrationFileNameStr)) {
            return true;
        }
        return unlink($MigrationFileNameStr);
    }
    protected function getMigrationFileNameFromBuffer(BufferedOutput $BufferedOutputObj): string {
        $MigrationPathStr = Str::between($BufferedOutputObj->fetch(), "[", "]");
        $MigrationPathArr = explode(DIRECTORY_SEPARATOR, $MigrationPathStr);
        return end($MigrationPathArr);
    }
}
