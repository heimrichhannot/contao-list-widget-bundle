<?php

namespace HeimrichHannot\ListWidgetBundle\Widget;


use Contao\BackendTemplate;
use Contao\Controller;
use Contao\Database;
use Contao\Input;
use Contao\Model;
use Contao\System;
use Contao\Template;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Response\ResponseData;
use HeimrichHannot\AjaxBundle\Response\ResponseSuccess;
use HeimrichHannot\UtilsBundle\Util\Utils;

class ListWidget extends Widget
{
    const LOAD_ACTION = 'list-load';
    const SKIP_FIELDS = ['id', 'tstamp', 'pid', 'dateAdded'];

    protected $blnForAttribute = true;
    protected $strTemplate = 'be_widget';
    protected string $strListTemplate = 'list_widget';
    protected array $arrDca;

    public function __construct($arrData)
    {
        Controller::loadDataContainer($arrData['strTable']);
        $this->arrDca = $GLOBALS['TL_DCA'][$arrData['strTable']]['fields'][$arrData['strField']]['eval']['listWidget'] ?? [];

        parent::__construct($arrData);
    }

    /**
     * Generate the widget and return it as string
     *
     * @return string
     */
    public function generate(): string
    {
        $objTemplate = new BackendTemplate($this->arrDca['template'] ?? $this->strListTemplate);

        $arrConfig = $this->arrDca;

        // no id necessary for identifier since a backend widget can only be available once in a palette
        $arrConfig['identifier'] = $this->name;

        $arrConfig = static::prepareConfig($arrConfig, $this, $this->objDca);

        if ($arrConfig['ajax']) {
            static::initAjaxLoading(
                $arrConfig,
                $this,
                $this->objDca
            );

        }

        static::addToTemplate($objTemplate, $arrConfig);

        return $objTemplate->parse();
    }

    public static function prepareConfig($arrConfig = [], $objContext = null, $objDca = null)
    {
        $arrConfig = array_merge([
            'useDbAsHeader' => false,
            'table' => null,
            'ajax' => false,
        ], $arrConfig);

        $arrConfig = $arrConfig ?: [];

        // header
        $arrConfig['headerFields'] = Polyfill::utilsV2_dcaUtil_getConfigByArrayOrCallbackOrFunction($arrConfig, 'header_fields', [$arrConfig, $objContext, $objDca]);

        if ($arrConfig['useDbAsHeader'] && $arrConfig['table']) {
            $strTable        = $arrConfig['table'];
            $arrFields       = Database::getInstance()->getFieldNames($strTable, true);
            $arrHeaderFields = [];

            foreach ($arrFields as $strField) {
                if (in_array($strField, static::SKIP_FIELDS)) {
                    continue;
                }

                $arrHeaderFields[$strField] = Polyfill::utilsV2_dcaUtil_getLocalizedFieldName($strField, $strTable);
            }

            $arrConfig['headerFields'] = $arrHeaderFields;
        }

        if (!$arrConfig['ajax']) {
            $arrConfig['items'] = Polyfill::utilsV2_dcaUtil_getConfigByArrayOrCallbackOrFunction($arrConfig, 'items', [$arrConfig, $objContext, $objDca]);
        }

        $arrConfig['language'] = Polyfill::utilsV2_dcaUtil_getConfigByArrayOrCallbackOrFunction($arrConfig, 'language', [$arrConfig, $objContext, $objDca]);

        $arrConfig['columns'] = Polyfill::utilsV2_dcaUtil_getConfigByArrayOrCallbackOrFunction($arrConfig, 'columns', [$arrConfig, $objContext, $objDca]);

        // prepare columns -> if not specified, get it from header fields
        if (!$arrConfig['columns']) {
            if (is_array($arrConfig['headerFields'])) {
                $arrColumns = [];
                $i          = 0;

                foreach ($arrConfig['headerFields'] as $strField => $strLabel) {
                    $arrColumns[] = [
                        'name'       => $strLabel,
                        'db'         => $strField,
                        'dt'         => $i++,
                        'searchable' => true,
                        'className'  => is_numeric($strField) ? 'col_' . $strField : $strField,
                    ];
                }

                $arrConfig['columns'] = $arrColumns;
            }
        }

        return $arrConfig;
    }

    public static function initAjaxLoading(array $arrConfig, $objContext = null, $objDc = null): void
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
        $dcaUtil = System::getContainer()->get('huh.utils.dca');

        if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
            return;
        }

        if (Input::get('key') !== static::LOAD_ACTION) {
            return;
        }

        if (Input::get('scope') !== $arrConfig['identifier']) {
            return;
        }

        $objResponse = new ResponseSuccess();

        // start loading
        if (!isset($arrConfig['ajaxConfig']['load_items_callback'])) {
            $arrConfig['ajaxConfig']['load_items_callback'] = function () use ($arrConfig, $objContext, $objDc) {
                return self::loadItems($arrConfig, [], $objContext, $objDc);
            };
        }

        $strResult = $dcaUtil->getConfigByArrayOrCallbackOrFunction(
            $arrConfig['ajaxConfig'],
            'load_items',
            [$arrConfig, [], $objContext, $objDc]
        );

        $objResponse->setResult(new ResponseData('', $strResult));
        $objResponse->output();
    }

    /**
     * Add widget setup to template
     *
     * Configuration:
     * - loadAssets: (bool) Load assets (default: true)
     */
    public static function addToTemplate(Template $objTemplate, array $configuration): void
    {
        $configuration = array_merge([
            'loadAssets' => true,
            'class' => '',
            'ajax' => false,
            'headerFields' => [],
            'columns' => [],
            'language' => '',
        ], $configuration);

        $objTemplate->class        = $configuration['class'];
        $objTemplate->ajax         = $configuration['ajax'];
        $objTemplate->headerFields = $configuration['headerFields'];
        $objTemplate->columnDefs   = htmlentities(json_encode(static::getColumnDefsData($configuration['columns'])));
        $objTemplate->language     = htmlentities(json_encode($configuration['language']));

        if ($configuration['ajax'])
        {
            $requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
            $queryString = sprintf('key=%s&scope=%s&rt=%s', static::LOAD_ACTION, $configuration['identifier'], $requestToken);
            $utils = System::getContainer()->get(Utils::class);
            $objTemplate->processingAction = $utils->url()->addQueryStringParameterToUrl($queryString);
        }
        else
        {
            $objTemplate->items = $configuration['items'];
        }

        if ($configuration['loadAssets']) {
            $GLOBALS['TL_JAVASCRIPT']['datatables-i18n']       = 'assets/datatables-additional/datatables-i18n/datatables-i18n.min.js';
            $GLOBALS['TL_JAVASCRIPT']['datatables-core']       = 'assets/datatables/datatables/media/js/jquery.dataTables.min.js';
            $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js';

            $GLOBALS['TL_JAVASCRIPT']['jquery.list_widget.js'] = 'bundles/heimrichhannotlistwidget/assets/js/jquery.list_widget.js';

            $GLOBALS['TL_CSS']['datatables-core']       = 'assets/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
            $GLOBALS['TL_CSS']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';
        }
    }

    public static function loadItems($arrConfig, $arrOptions = [], $objContext = null, $objDc = null): array
    {
        $arrOptions = !empty($arrOptions)
            ? $arrOptions
            : [
                'table'   => $arrConfig['table'],
                'columns' => $arrConfig['columns'],
            ];

        $objItems                       = static::fetchItems($arrOptions);
        $arrResponse                    = [];
        $getDraw                        = Input::get('draw');
        $arrResponse['draw']            = ($getDraw && is_string($getDraw)) ? intval($getDraw) : 0;
        $arrResponse['recordsTotal']    = intval(static::countTotal($arrOptions));
        $arrResponse['recordsFiltered'] = intval(static::countFiltered($arrOptions));

        // prepare
        if (!isset($arrConfig['ajaxConfig']['prepare_items_callback'])) {
            $arrConfig['ajaxConfig']['prepare_items_callback'] = function () use ($objItems, $arrConfig, $arrOptions, $objContext, $objDc) {
                return self::prepareItems($objItems, $arrConfig, $arrOptions, $objContext, $objDc);
            };
        }

        $arrResponse['data'] = System::getContainer()->get('huh.utils.dca')->getConfigByArrayOrCallbackOrFunction(
            $arrConfig['ajaxConfig'],
            'prepare_items',
            [
                $objItems,
                $arrConfig,
                $arrOptions,
                $objContext,
                $objDc,
            ]
        );

        return $arrResponse;
    }

    protected static function prepareItems($objItems, $arrConfig, $arrOptions = [], $objContext = null, $objDc = null)
    {
        if ($objItems === null) {
            return [];
        }

        $arrItems = [];

        while ($objItems->next()) {
            $objItem = $objItems->current();
            $arrItem = [];

            foreach ($arrConfig['columns'] as $arrColumn) {
                $arrItem[] = [
                    'value' => $objItem->{$arrColumn['db']},
                ];
            }

            $arrItems[] = $arrItem;
        }

        return $arrItems;
    }

    protected static function getColumnDefsData($arrColumns)
    {
        $arrayUtil = System::getContainer()->get('huh.utils.array');
        $arrConfig = [];

        foreach ($arrColumns as $i => $arrColumn) {
            $arrConfig[] = array_merge(
                $arrayUtil->filterByPrefixes($arrColumn, ['searchable', 'className', 'orderable', 'type']),
                ['targets' => $arrColumn['dt']],
                ['render' => ['_' => 'value']]
            );
        }

        return $arrConfig;
    }

    /**
     * Count the total matching items
     *
     * @param array $options
     * @return int
     */
    protected static function countTotal(array $options): int
    {
        $options = array_merge([
            'column' => [],
            'value' => [],
        ], $options);

        /** @var class-string<Model> $modelClass */
        $modelClass = Model::getClassFromTable($options['table']);

        if (isset($options['column'])) {
            return $modelClass::countBy($options['column'], $options['value'], $options);
        } else {
            return $modelClass::countAll();
        }
    }

    /**
     * Count the filtered items
     *
     * @param  array $options SQL options
     *
     * @return integer
     */
    protected static function countFiltered(array $options): int
    {
        unset($options['limit']);
        unset($options['offset']);

        $options = array_merge([
            'column' => [],
            'value' => [],
        ], $options);

        /** @var class-string<Model> $modelClass */
        $modelClass = Model::getClassFromTable($options['table']);

        if (isset($options['column'])) {
            return $modelClass::countBy($options['column'], $options['value'], $options);
        } else {
            return $modelClass::countAll();
        }
    }

    /**
     * Fetch the matching items
     *
     * @param  array $arrOptions SQL options
     *
     * @return array          Server-side processing response array
     */
    protected static function fetchItems(&$arrOptions = [])
    {
        $arrOptions = static::limitSQL($arrOptions);
        $arrOptions = static::filterSQL($arrOptions);
        $arrOptions = static::orderSQL($arrOptions);

        /** @var class-string<Model> $modelClass */
        $strModel = Model::getClassFromTable($arrOptions['table']);

        return $strModel::findAll($arrOptions);
    }

    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL query
     *
     * @param  array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with limit clause
     */
    protected static function limitSQL($arrOptions)
    {
        $start = Input::get('start');
        $length = Input::get('length');
        if ($start && $length != -1) {
            $arrOptions['offset'] = $start;
            $arrOptions['limit']  = $length;
        }

        return $arrOptions;
    }

    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     * @param  array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with where conditions (values and columns)
     */
    protected static function filterSQL($arrOptions)
    {
        $t = $arrOptions['table'];

        $columns      = $arrOptions['columns'];
        $globalSearch = [];
        $columnSearch = [];
        $dtColumns    = self::pluck($columns, 'dt');

        $search = Input::get('search');
        $getColumns = Input::get('columns');
        $getColumns = is_array($getColumns) ? $getColumns : [];

        if ($search && is_array($search) && ($str = $search['value']) != '')
        {
            for ($i = 0, $ien = count($getColumns); $i < $ien; $i++)
            {
                $requestColumn = $getColumns[$i];
                $columnIdx     = array_search($requestColumn['data'], $dtColumns);
                $column        = $columns[$columnIdx];

                if (!($column['db'] ?? null)) {
                    continue;
                }

                if ($requestColumn['searchable'] == 'true') {
                    $globalSearch[] = "$t." . $column['db'] . " LIKE '%%" . $str . "%%'";
                }
            }
        }
        // Individual column filtering
        if (!empty($getColumns))
        {
            for ($i = 0, $ien = count($getColumns); $i < $ien; $i++)
            {
                $requestColumn = $getColumns[$i];
                $columnIdx     = array_search($requestColumn['data'], $dtColumns);
                $column        = $columns[$columnIdx];
                $str           = $requestColumn['search']['value'] ?? null;

                if (!($column['db'] ?? null)) {
                    continue;
                }

                if ($requestColumn['searchable'] == 'true' && $str != '') {
                    $columnSearch[] = "$t." . $column['db'] . " LIKE '%%" . $str . "%%'";
                }
            }
        }
        // Combine the filters into a single string
        $where = '';
        if (count($globalSearch)) {
            $where = '(' . implode(' OR ', $globalSearch) . ')';
        }

        if (count($columnSearch)) {
            $where = $where === '' ? implode(' AND ', $columnSearch) : $where . ' AND ' . implode(' AND ', $columnSearch);
        }

        if (isset($arrOptions['column'])) {
            $arrOptions['column'] = is_array($arrOptions['column']) ? $arrOptions['column'] : [$arrOptions['column']];
        } else {
            $arrOptions['column'] = [];
        }

        if ($where) {
            $arrOptions['column'] = array_merge($arrOptions['column'], [$where]);
        }

        if (empty($arrOptions['column'])) {
            $arrOptions['column'] = null;
        }

        return $arrOptions;
    }

    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     * @param array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with order conditions
     */
    protected static function orderSQL(array $arrOptions): array
    {
        $t       = $arrOptions['table'];
        $columns = $arrOptions['columns'];

        $getOrder = Input::get('order');
        $getColumns = Input::get('columns');

        if (!is_array($getOrder) || !$getOrder || $getColumns === null) {
            return $arrOptions;
        }

        $orderBy   = [];
        $dtColumns = static::pluck($columns, 'dt');
        for ($i = 0, $ien = count($getOrder); $i < $ien; $i++)
        {
            // Convert the column index into the column data property
            $columnIdx = intval($getOrder[$i]['column']);
            $requestColumn = $getColumns[$columnIdx] ?? null;

            if ($requestColumn === null) {
                continue;
            }

            $columnIdx = array_search($requestColumn['data'], $dtColumns);
            $column = $columns[$columnIdx] ?? null;

            if (!($column['db'] ?? null)) {
                continue;
            }

            if ($requestColumn['orderable'] == 'true')
            {
                $dir = ($getOrder[$i]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

                if ($column['name'] == 'transport') {
                    $orderBy[] = "GREATEST($t." . $column['db'] . ", $t.transportTime) " . $dir;
                } else {
                    $orderBy[] = "$t." . $column['db'] . " " . $dir;
                }

            }
        }

        if ($orderBy) {
            $arrOptions['order'] = implode(', ', $orderBy);
        }

        return $arrOptions;
    }


    /**
     * Pull a particular property from each assoc. array in a numeric array,
     * returning and array of the property values from each item.
     *
     * @param array $data Array to get data from
     * @param string $property Property to read
     *
     * @return array        Array of property values
     */
    protected static function pluck(array $data, string $property): array
    {
        $out = [];
        for ($i = 0, $len = count($data); $i < $len; $i++) {
            $out[] = $data[$i][$property];
        }

        return $out;
    }
}
