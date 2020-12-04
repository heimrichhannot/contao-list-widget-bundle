<?php

/**
 * Backend form fields
 */
$GLOBALS['BE_FFL']['listWidget'] = 'HeimrichHannot\ListWidget\ListWidget';

/**
 * Assets
 */
if (TL_MODE == 'BE')
{
    $strBasePath = version_compare(VERSION, '4.0', '<') ? 'assets/components' : 'assets';

    $GLOBALS['TL_JAVASCRIPT']['datatables-i18n']       =
        $strBasePath . '/datatables-additional/datatables-i18n/datatables-i18n.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-core']       = $strBasePath . '/datatables/datatables/media/js/jquery.dataTables.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] =
        $strBasePath . '/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js';

    $GLOBALS['TL_JAVASCRIPT']['jquery.list_widget.js'] = 'system/modules/list_widget/assets/js/jquery.list_widget.js';

    $GLOBALS['TL_CSS']['datatables-core']       =
        $strBasePath . '/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
    $GLOBALS['TL_CSS']['datatables-rowReorder'] =
        $strBasePath . '/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';
}
