<?php

namespace Divblox\Classes;

use Closure;

abstract class Helper {
    public static function processConfigTransformer(string $ConfigLocationStr, mixed $PreTransformedInputMix): mixed {
        $DivbloxConfigMix = config($ConfigLocationStr);
        if (empty($DivbloxConfigMix)) {
            return $PreTransformedInputMix;
        }
        if (!is_array($DivbloxConfigMix)) {
            return $PreTransformedInputMix;
        }

        foreach ($DivbloxConfigMix as $TransformingClassStr => $TransformingConfigMix) {
            switch (gettype($TransformingConfigMix)) {
                case "string":
                    $PreTransformedInputMix = $TransformingClassStr::$TransformingConfigMix($PreTransformedInputMix);
                break;
                case "array":
                    foreach ($TransformingConfigMix as $TransformingConfigStr) {
                        $PreTransformedInputMix = $TransformingClassStr::$TransformingConfigStr($PreTransformedInputMix);
                    }
                break;
            }
        }
        return $PreTransformedInputMix;
    }
}