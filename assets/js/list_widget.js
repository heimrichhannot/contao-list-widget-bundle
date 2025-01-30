import DataTable from 'datatables.net-dt';
import languageDE from 'datatables.net-plugins/i18n/de-DE.js';
import 'datatables.net-dt/css/dataTables.dataTables.css';

function initWidget() {
    let widgets = document.querySelectorAll('.list-widget');
    let locale = document.querySelector('html').getAttribute('lang');

    widgets.forEach(widget => {
        let table = widget.querySelector('table');
        if (!table) {
            return;
        }

        let options = {};
        options.stateSave = true;
        options.pagingType = 'full_numbers';

        let language = {};
        if ('de' === locale) {
            language = languageDE;
        }
        if (widget.dataset.language) {
            let customLang = JSON.parse(widget.dataset.language);

            if ('sLengthMenu' in customLang || 'sEmptyTable' in customLang || 'sInfo' in customLang) {
                console.warn('Language string changed in datatables version 1.10. Please adjust your language strings for list widget bundle! See https://datatables.net/upgrade/1.10-convert.');
            }

            language = Object.assign(language, customLang);
        }
        options.language = language;

        if (widget.dataset.ajax == 1) {
            options.processing = true;
            options.serverSide = true;
            options.ajax = {
                url: widget.dataset.processingAction,
                dataFilter: function(data) {
                    if (!data) {
                        return [];
                    }

                    let json = JSON.parse(data);
                    return JSON.stringify(json.result.data);
                }
            };
            options.columnDefs = JSON.parse(widget.dataset.columnDefs);
            options.order = [[0, 'desc']];

            options.createdRow = function(row, data, rowIndex) {
                row.querySelectorAll('td').forEach((cell, colIndex) => {
                    if (!data[colIndex].attributes)
                    {
                        return true;
                    }

                    Object.entries(data[colIndex].attributes).forEach(([key, value]) => {
                        cell.setAttribute(key, value);
                    });
                });
            };

        }

        if (!DataTable.isDataTable(table)) {
            new DataTable(table, options);
        }
    });
}

document.addEventListener('DOMContentLoaded', initWidget);
