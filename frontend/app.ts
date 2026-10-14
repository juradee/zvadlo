import './scss/app.scss';
import 'bootstrap';
import naja from 'naja';
import netteForms from 'nette-forms';

import { initCheckAll } from './ts/checkAll';
import { initConfirmButtons } from './ts/confirm';
import { initCopyButtons } from './ts/copy';
import { initDatePickers } from './ts/datePicker';
import { initForms } from './ts/forms';
import { initLogoutTimer } from './ts/logoutTimer';
import { initPwa } from './ts/pwa';
import { initRoleTooltip } from './ts/roleTooltip';
import { initMemberAddresses } from './ts/memberAddresses';
import { initScopeFilter } from './ts/scopeFilter';
import { initSessionKeepAlive } from './ts/sessionKeepAlive';
import { initBatchRunner } from './ts/batchRunner';
import { initBugReportDiagnostics } from './ts/bugReportDiagnostics';
import { initTaskProgress } from './ts/taskProgress';

declare global {
    interface Window {
        Nette: typeof netteForms;
    }
}

window.Nette = netteForms;
netteForms.initOnLoad();

naja.formsHandler.netteForms = netteForms;
naja.initialize();

initForms();
initCheckAll();
initConfirmButtons();
initCopyButtons();
initDatePickers();
initBatchRunner();
initBugReportDiagnostics();
initTaskProgress();
initSessionKeepAlive();
initLogoutTimer();
initPwa();
initRoleTooltip();
initMemberAddresses();
initScopeFilter();
