(function($)
{
    ListWidget = {
        init: function()
        {
            this.initWidget();
        },
        initWidget: function()
        {
            $('.list-widget table').each(function()
            {
                var $table = $(this),
                    $listWidget = $table.closest('.list-widget'),
                    options,
                    language = $('html').attr('lang');

                var messages = DATATABLE_MESSAGES[language] ? DATATABLE_MESSAGES[language] : DATATABLE_MESSAGES['en'];

                if (typeof $listWidget.data('language') !== 'undefined' && $listWidget.data('language')) {
                    messages = $.extend(messages, $listWidget.data('language'));
                    delete $listWidget.data('language');
                }

                options = {
                    language: messages,
                    stateSave: true,
                    pagingType: 'full_numbers'
                };

                if ($listWidget.data('ajax') == 1)
                {
                    options = $.extend({}, options, {
                        processing: true,
                        serverSide: true,
                        ajax: {
                            url: $listWidget.data('processing-action'),
                            dataFilter: function(data)
                            {
                                if (!data)
                                {
                                    return [];
                                }

                                var json = JSON.parse(data);

                                return JSON.stringify(json.result.data);
                            }
                        },
                        columnDefs: $listWidget.data('column-defs'),
                        order: [[0, 'desc']],
                        // Per-row function to iterate cells
                        createdRow: function(row, data, rowIndex)
                        {
                            // Per-cell function to do whatever needed with cells
                            $.each($('td', row), function(colIndex)
                            {
                                if (!data[colIndex].attributes)
                                {
                                    return true;
                                }

                                $(this).attr(data[colIndex].attributes);
                            });
                        }
                    });
                }

                if ($.fn.dataTable.isDataTable(this))
                {
                    $(this).DataTable();
                }
                else
                {
                    $(this).DataTable(options);
                }
            });
        },
        removeEmptyColumns: function($table) {
            // initially show all
            $table.find('tr').show();

            $table.find('th').each(function (i) {
                var remove = 0,
                    tds = $(this).closest('table').find('tr td:nth-child(' + (i + 1) + ')');

                tds.each(function () {
                    if (this.innerHTML == '') {
                        remove++;
                    }
                });

                if (remove == ($table.find('tr').length - 1)) {
                    $(this).hide();
                    tds.hide();
                }
            });
        }
    };

    $(document).ready(function()
    {
        ListWidget.init();
    });

})(jQuery);
