<?php
return [
    "data_design" => [
        "transformers" => [
            "table" => [
                "class" => null,
                "method" => null
            ],
            "column" => null,
            "index" => fn($value) => $value
        ]
    ]
];