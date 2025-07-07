// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * TODO describe module mapping_actions_bulk
 *
 * @module     local_sync_courseleaders/mapping_actions_bulk
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {prefetchStrings} from 'core/prefetch';
import {getList} from 'core/normalise';
import Notification from 'core/notification';
import Ajax from 'core/ajax';
import Url from 'core/url';

const selectors = {
    activeRows: 'input.mappingcheckbox:checked',
    bulkActions: '#courseleadersmappingsbulkactions',
    table: '#coursemodulemappings_table',
};

export const init = () => {
    prefetchStrings('local_sync_courseleaders', [
        'disable',
        'enable',
    ]);
    prefetchStrings('core', [
        'delete',
    ]);
    registerEventListeners();
};

/**
 * Register event listeners for bulk deleting
 */
const registerEventListeners = () => {
    document.querySelector(selectors.bulkActions)?.addEventListener('change', e => {
        const action = e.target;
        if (action.value.indexOf('#') !== -1) {
            e.preventDefault();
            if (action.value == '#disableselect') {
                disableMappings();
            }
            if (action.value == '#enableselect') {
                enableMappings();
            }
        }
    });
};

/**
 * Confirm delete activities dialogue.
 */
const enableMappings = () => {
    const table = document.querySelector(selectors.table);
    const activeRows = getList(table.querySelectorAll(selectors.activeRows));
    const ids = activeRows.map(item => item.value);
    return updateMappings(ids, 1);
};

const disableMappings = () => {
    const table = document.querySelector(selectors.table);
    const activeRows = getList(table.querySelectorAll(selectors.activeRows));
    const ids = activeRows.map(item => item.value);
    return updateMappings(ids, 0);
};

/**
 * Update mappings
 * @param {Number[]} ids Mapping ids
 * @param {Number} enabled Enable or Disable
 */
async function updateMappings(ids, enabled) {
    const request = {
        methodname: 'local_sync_courseleaders_update_mappings',
        args: {
            mappingids: ids,
            enabled: enabled
        }
    };
    try {
        await Ajax.call([request])[0];
        window.location.href = Url.relativeUrl(
            'local/sync_courseleaders/index.php',
            {},
            false
        );
    } catch (error) {
        Notification.exception(error);
    }
}

