<?php

namespace HeimrichHannot\ListWidgetBundle\Widget;

use Contao\Controller;
use Contao\System;

class Polyfill
{
    /**
     * Retrieves an array from a dca config (in most cases eval) in the following priorities:.
     *
     * 1. The value associated to $array[$property]
     * 2. The value retrieved by $array[$property . '_callback'] which is a callback array like ['Class', 'method'] or ['service.id', 'method']
     * 3. The value retrieved by $array[$property . '_callback'] which is a function closure array like ['Class', 'method']
     */
    public static function utilsV2_dcaUtil_getConfigByArrayOrCallbackOrFunction(array $array, $property, array $arguments = []): mixed
    {
        if (isset($array[$property])) {
            return $array[$property];
        }

        if (!isset($array[$property.'_callback'])) {
            return null;
        }

        if (\is_array($array[$property.'_callback'])) {
            $callback = $array[$property.'_callback'];

            if (!isset($callback[0]) || !isset($callback[1])) {
                return null;
            }

            try {
                $instance = Controller::importStatic($callback[0]);
            } catch (\Exception $e) {
                return null;
            }

            if (!method_exists($instance, $callback[1])) {
                return null;
            }

            try {
                return \call_user_func_array([$instance, $callback[1]], $arguments);
            } catch (\Error $e) {
                return null;
            }
        } elseif (\is_callable($array[$property.'_callback'])) {
            try {
                return \call_user_func_array($array[$property.'_callback'], $arguments);
            } catch (\Error $e) {
                return null;
            }
        }

        return null;
    }

    public static function utilsV2_dcaUtil_getLocalizedFieldName(string $strField, string $strTable): mixed
    {
        Controller::loadDataContainer($strTable);
        System::loadLanguageFile($strTable);

        return ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['label'][0] ?? $strField) ?: $strField;
    }
}