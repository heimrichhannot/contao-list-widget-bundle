<?php

/**
 * Backend form fields
 */
$GLOBALS['BE_FFL']['listWidget'] = \HeimrichHannot\ListWidgetBundle\Widget\ListWidget::class;

/**
 * Assets
 */
if (TL_MODE == 'BE')
{
    $GLOBALS['TL_JAVASCRIPT']['datatables-i18n']       =
        'assets/datatables-additional/datatables-i18n/datatables-i18n.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-core']       = 'assets/datatables/datatables/media/js/jquery.dataTables.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] =
        'assets/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js';

    $GLOBALS['TL_JAVASCRIPT']['jquery.list_widget.js'] = 'bundles/heimrichhannotlistwidget/assets/js/jquery.list_widget.js';

    $GLOBALS['TL_CSS']['datatables-core']       =
        'assets/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
    $GLOBALS['TL_CSS']['datatables-rowReorder'] =
        'assets/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';
}
