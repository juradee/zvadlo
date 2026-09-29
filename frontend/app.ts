import './scss/app.scss';
import 'bootstrap';
import naja from 'naja';
import netteForms from 'nette-forms';

import { initForms } from './ts/forms';

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
