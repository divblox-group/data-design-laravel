<?php
return [
    "data_design" => [
        "transformers" => [
            "model" => [
                "class" => Illuminate\Support\Str::class,
                "method" => "pascal"
            ],
            "table" => [
                "class" => null,
                "method" => null
            ],
            "column" => null,
            "index" => fn($value) => $value
        ]
    ]
];