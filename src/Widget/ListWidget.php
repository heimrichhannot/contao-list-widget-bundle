<?php

namespace HeimrichHannot\ListWidgetBundle\Widget;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;
use Contao\System;
use Contao\Template;
use Contao\Widget;
use HeimrichHannot\AjaxBundle\Response\ResponseData;
use HeimrichHannot\AjaxBundle\Response\ResponseSuccess;
use HeimrichHannot\UtilsBundle\Util\Utils;

class ListWidget extends Widget
{
    public const TYPE = 'listWidget';

    public const LOAD_ACTION = 'list-load';

    protected $blnForAttribute = true;

    protected $strTemplate = 'be_widget';

    protected $strListTemplate = 'list_widget';

    protected $arrDca;

    protected $arrWidgetErrors = [];

    protected static $arrSkipFields = ['id', 'tstamp', 'pid', 'dateAdded'];

    public function __construct($arrData)
    {
        Controller::loadDataContainer($arrData['strTable']);
        $this->arrDca = $GLOBALS['TL_DCA'][$arrData['strTable']]['fields'][$arrData['strField']]['eval']['listWidget'];

        parent::__construct($arrData);
    }

    /**
     * Generate the widget and return it as string.
     *
     * @return string
     */
    public function generate()
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

        $arrConfig['ptable'] = $this->strTable;
        $arrConfig['pid'] = $this->objDca->id;
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

        $utils = System::getContainer()->get(Utils::class);

        if (!isset($arrConfig['headerFields'])) {
            $arrConfig['headerFields'] = $utils->dca()->executeCallback(
                $arrConfig['header_fields_callback'] ?? null,
                $arrConfig, $objContext, $objDca
            );
        }

        if ($arrConfig['useDbAsHeader'] && $arrConfig['table']) {
            $strTable = $arrConfig['table'];
            $arrFields = Database::getInstance()->getFieldNames($strTable, true);
            $arrHeaderFields = [];

            Controller::loadDataContainer($strTable);
            System::loadLanguageFile($strTable);

            foreach ($arrFields as $strField) {
                if (in_array($strField, static::$arrSkipFields)) {
                    continue;
                }

                $arrHeaderFields[$strField] = $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['label'][0] ?: $strField;
            }

            $arrConfig['headerFields'] = $arrHeaderFields;
        }

        if (!$arrConfig['ajax']) {
            if (!isset($arrConfig['items'])) {
                $arrConfig['items'] = $utils->dca()->executeCallback(
                    $arrConfig['items_callback'] ?? null,
                    $arrConfig, $objContext, $objDca
                );
            }
        }

        if (!isset($arrConfig['language'])) {
            $arrConfig['language'] = $utils->dca()->executeCallback(
                $arrConfig['language_callback'] ?? null,
                $arrConfig, $objContext, $objDca
            );
        }

        if (!isset($arrConfig['columns'])) {
            $arrConfig['columns'] = $utils->dca()->executeCallback(
                $arrConfig['columns_callback'] ?? null,
                $arrConfig, $objContext, $objDca
            );
        }

        // prepare columns -> if not specified, get it from header fields
        if (!$arrConfig['columns']) {
            if (is_array($arrConfig['headerFields'])) {
                $arrColumns = [];
                $i = 0;

                foreach ($arrConfig['headerFields'] as $strField => $strLabel) {
                    $arrColumns[] = [
                        'name' => $strLabel,
                        'db' => $strField,
                        'dt' => $i++,
                        'searchable' => true,
                        'className' => is_numeric($strField) ? 'col_' . $strField : $strField,
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
        if (!$request) {
            return;
        }

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        if (ListWidget::LOAD_ACTION == $request->query->get('key') && $request->query->get('scope') == $arrConfig['identifier']) {
            $objResponse = new ResponseSuccess();

            // start loading
            if (!isset($arrConfig['ajaxConfig']['load_items_callback'])) {
                $arrConfig['ajaxConfig']['load_items_callback'] = fn () => self::loadItems($arrConfig, [], $objContext, $objDc);
            }

            $strResult = System::getContainer()->get(Utils::class)->dca()->executeCallback(
                $arrConfig['ajaxConfig']['load_items_callback'] ?? null,
                $arrConfig, [], $objContext, $objDc
            );

            $objResponse->setResult(new ResponseData('', $strResult));
            $objResponse->output();
        }
    }

    /**
     * Add widget setup to template.
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

        $objTemplate->class = $configuration['class'];
        $objTemplate->ajax = $configuration['ajax'];
        $objTemplate->headerFields = $configuration['headerFields'];
        $objTemplate->columnDefs = htmlentities(json_encode(static::getColumnDefsData($configuration['columns'])));
        $objTemplate->language = $configuration['language'] ? htmlentities(json_encode($configuration['language'])) : null;

        if ($configuration['ajax'] ?? false) {
            if ($configuration['ajaxConfig']['route'] ?? false) {
                $objTemplate->processingAction = System::getContainer()->get('router')->generate(
                    $configuration['ajaxConfig']['route'],
                    [
                        'scope' => $configuration['identifier'],
                        'table' => $configuration['ptable'],
                        'id' => $configuration['pid'],
                    ]
                );
            } else {
                $objTemplate->processingAction = System::getContainer()->get(Utils::class)->url()->addQueryStringParameterToUrl(
                    'key=' . static::LOAD_ACTION . '&scope=' . $configuration['identifier'] . '&rt=' . System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue()
                );
            }
        } else {
            $objTemplate->items = $configuration['items'];
        }

        if ($configuration['loadAssets']) {
            $GLOBALS['TL_JAVASCRIPT']['contao-list-widget'] = 'bundles/heimrichhannotlistwidget/assets/contao-list-widget-bundle.js';
            $GLOBALS['TL_CSS']['datatables-core'] = 'bundles/heimrichhannotlistwidget/assets/contao-list-widget-bundle.css';
        }
    }

    public static function loadItems($arrConfig, $arrOptions = [], $objContext = null, $objDc = null)
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
        if (!$request) {
            return [];
        }

        $arrOptions = !empty($arrOptions)
            ? $arrOptions
            : [
                'table' => $arrConfig['table'],
                'columns' => $arrConfig['columns'],
            ];

        $objItems = static::fetchItems($arrOptions);
        $arrResponse = [];
        $arrResponse['draw'] = $request->query->has('draw') ? intval($request->query->get('draw')) : 0;
        $arrResponse['recordsTotal'] = intval(static::countTotal($arrOptions));
        $arrResponse['recordsFiltered'] = intval(static::countFiltered($arrOptions));

        // prepare
        if (!isset($arrConfig['ajaxConfig']['prepare_items_callback'])) {
            $arrConfig['ajaxConfig']['prepare_items_callback'] = fn () => self::prepareItems($objItems, $arrConfig, $arrOptions, $objContext, $objDc);
        }

        $arrResponse['data'] = $arrConfig['ajaxConfig']['prepare_items']
            ?? System::getContainer()->get(Utils::class)->dca()->executeCallback(
                $arrConfig['ajaxConfig']['prepare_items_callback'] ?? null,
                $objItems,
                $arrConfig,
                $arrOptions,
                $objContext,
                $objDc,
            );

        return $arrResponse;
    }

    protected static function prepareItems($objItems, $arrConfig, $arrOptions = [], $objContext = null, $objDc = null)
    {
        if (null === $objItems) {
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
        $arrConfig = [];

        foreach ($arrColumns as $i => $arrColumn) {
            $arrConfig[] = array_merge(
                static::filterByPrefixes($arrColumn, ['searchable', 'className', 'orderable', 'type']),
                [
                    'targets' => $arrColumn['dt'],
                ],
                [
                    'render' => [
                        '_' => 'value',
                    ],
                ]
            );
        }

        return $arrConfig;
    }

    /**
     * Count the total matching items.
     */
    protected static function countTotal(array $options): int
    {
        $options = array_merge([
            'column' => [],
            'value' => [],
        ], $options);

        $modelClass = Model::getClassFromTable($options['table']);

        if (isset($options['column'])) {
            return $modelClass::countBy($options['column'], $options['value'], $options);
        } else {
            return $modelClass::countAll();
        }
    }

    /**
     * Count the filtered items.
     *
     * @param array $options SQL options
     */
    protected static function countFiltered(array $options): int
    {
        unset($options['limit']);
        unset($options['offset']);

        $options = array_merge([
            'column' => [],
            'value' => [],
        ], $options);

        $modelClass = Model::getClassFromTable($options['table']);

        if (isset($options['column'])) {
            return $modelClass::countBy($options['column'], $options['value'], $options);
        } else {
            return $modelClass::countAll();
        }
    }

    /**
     * Fetch the matching items.
     *
     * @param array $arrOptions SQL options
     *
     * @return Collection|null Server-side processing response array
     */
    protected static function fetchItems(array &$arrOptions = []): ?Collection
    {
        $arrOptions = static::limitSQL($arrOptions);
        $arrOptions = static::filterSQL($arrOptions);
        $arrOptions = static::orderSQL($arrOptions);

        $strModel = Model::getClassFromTable($arrOptions['table']);

        return $strModel::findAll($arrOptions);
    }

    /**
     * Paging.
     *
     * Construct the LIMIT clause for server-side processing SQL query
     *
     * @param array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with limit clause
     */
    protected static function limitSQL($arrOptions)
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
        if ($request->query->has('start') && -1 != $request->query->get('length')) {
            $arrOptions['limit'] = (int) $request->query->get('length');
            $arrOptions['offset'] = (int) $request->query->get('start');
        }

        return $arrOptions;
    }

    /**
     * Searching / Filtering.
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     * @param array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with where conditions (values and columns)
     */
    protected static function filterSQL($arrOptions)
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        $t = $arrOptions['table'];

        $columns = $arrOptions['columns'];
        $globalSearch = [];
        $columnSearch = [];
        $dtColumns = self::pluck($columns, 'dt');
        $request = $request->query->all();

        if (isset($request['search']) && '' != $request['search']['value']) {
            $str = $request['search']['value'];
            for ($i = 0, $ien = count($request['columns']); $i < $ien; ++$i) {
                $requestColumn = $request['columns'][$i];
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $column = $columns[$columnIdx];

                if (!$column['db']) {
                    continue;
                }

                if ('true' == $requestColumn['searchable']) {
                    $globalSearch[] = "$t." . $column['db'] . " LIKE '%%" . $str . "%%'";
                }
            }
        }
        // Individual column filtering
        if (isset($request['columns'])) {
            for ($i = 0, $ien = count($request['columns']); $i < $ien; ++$i) {
                $requestColumn = $request['columns'][$i];
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $column = $columns[$columnIdx];
                $str = $requestColumn['search']['value'];

                if (!($column['db'] ?? null)) {
                    continue;
                }

                if ('true' == $requestColumn['searchable'] && '' != $str) {
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
            $where = '' === $where ? implode(' AND ', $columnSearch) : $where . ' AND ' . implode(' AND ', $columnSearch);
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
     * Ordering.
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     * @param array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with order conditions
     */
    protected static function orderSQL($arrOptions)
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        $t = $arrOptions['table'];
        $request = $request->query->all();
        $columns = $arrOptions['columns'];

        if (isset($request['order']) && count($request['order'])) {
            $orderBy = [];
            $dtColumns = static::pluck($columns, 'dt');
            for ($i = 0, $ien = count($request['order']); $i < $ien; ++$i) {
                // Convert the column index into the column data property
                $columnIdx = intval($request['order'][$i]['column']);
                $requestColumn = $request['columns'][$columnIdx];
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $column = $columns[$columnIdx];

                if (!$column['db']) {
                    continue;
                }

                if ('true' == $requestColumn['orderable']) {
                    $dir = 'asc' === $request['order'][$i]['dir'] ? 'ASC' : 'DESC';

                    if ('transport' == $column['name']) {
                        $orderBy[] = "GREATEST($t." . $column['db'] . ", $t.transportTime) " . $dir;
                    } else {
                        $orderBy[] = "$t." . $column['db'] . ' ' . $dir;
                    }
                }
            }

            if ($orderBy) {
                $arrOptions['order'] = implode(', ', $orderBy);
            }
        }

        return $arrOptions;
    }

    /**
     * Pull a particular property from each assoc. array in a numeric array,
     * returning and array of the property values from each item.
     *
     * @param array  $a    Array to get data from
     * @param string $prop Property to read
     *
     * @return array Array of property values
     */
    protected static function pluck($a, $prop)
    {
        $out = [];
        for ($i = 0, $len = count($a); $i < $len; ++$i) {
            $out[] = $a[$i][$prop];
        }

        return $out;
    }

    /**
     * @interal
     */
    public static function filterByPrefixes(array $data = [], $prefixes = [])
    {
        $extract = [];

        if (!\is_array($prefixes) || empty($prefixes)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, (string) $prefix)) {
                    $extract[$key] = $value;
                }
            }
        }

        return $extract;
    }
}
