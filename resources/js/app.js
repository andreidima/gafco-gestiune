import '../scss/app.scss';
import 'bootstrap';
import TomSelect from 'tom-select';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tom-select]').forEach((element) => {
        new TomSelect(element, {
            allowEmptyOption: true,
            create: false,
        });
    });
});
