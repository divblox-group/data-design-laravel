<?php

namespace Divblox\Classes;

use Closure;

abstract class Helper {
    public static function processConfigTransformer(string $ConfigLocationStr, mixed $PreTransformedInputMix): mixed {
        $DivbloxConfigMix = config($ConfigLocationStr);
        if (empty($DivbloxConfigMix)) {
            return $PreTransformedInputMix;
        }
        if (is_array($DivbloxConfigMix)) {
            if (empty($DivbloxConfigMix["class"]) ||
                empty($DivbloxConfigMix["method"])
            ) {
                return $PreTransformedInputMix;
            }
            return $DivbloxConfigMix["class"]::{$DivbloxConfigMix["method"]}($PreTransformedInputMix);
        }

        if ($DivbloxConfigMix instanceof Closure) {
            return $DivbloxConfigMix($PreTransformedInputMix);
        }
        return $PreTransformedInputMix;
    }
}