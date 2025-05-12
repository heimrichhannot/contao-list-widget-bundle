<?php

/**
 * Contao Open Source CMS.
 *
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @author  Thomas Körner <t.koerner@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\ListWidgetBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotListWidgetBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
