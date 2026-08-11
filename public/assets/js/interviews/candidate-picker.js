(function ($) {
    'use strict';

    function initCandidatePicker() {
        $('[data-candidate-picker]').each(function () {
            var $picker = $(this);
            var $source = $picker.find('[data-candidate-source]');
            var $results = $picker.find('[data-candidate-results]');
            var $selectedList = $picker.find('[data-candidate-selected-list]');
            var $empty = $picker.find('[data-candidate-empty]');
            var $selectedEmpty = $picker.find('[data-candidate-selected-empty]');
            var $count = $picker.find('[data-candidate-count]');
            var $search = $picker.find('[data-candidate-search]');
            var $company = $picker.find('[data-candidate-company-filter]');
            var candidates = [];
            var originalOptions = $source.find('option').toArray();
            var selectedOrder = $source.find('option:selected').map(function () {
                return String(this.value);
            }).get();
            $source.addClass('d-none').attr({ 'aria-hidden': 'true', tabindex: '-1' });

            originalOptions.forEach(function (option, index) {
                candidates.push({
                    id: String(option.value),
                    name: String($(option).data('candidate-name') || option.textContent || '').trim(),
                    rut: String($(option).data('candidate-rut') || '').trim(),
                    email: String($(option).data('candidate-email') || '').trim(),
                    company: String($(option).data('candidate-company') || '').trim(),
                    option: option,
                    index: index
                });
            });

            function normalize(value) {
                return String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            }

            function isSelected(id) {
                return selectedOrder.indexOf(String(id)) !== -1;
            }

            function syncSourceOrder() {
                var selectedSet = {};
                selectedOrder.forEach(function (id) {
                    selectedSet[String(id)] = true;
                });
                candidates.slice().sort(function (left, right) {
                    var leftSelected = selectedSet[left.id] ? 0 : 1;
                    var rightSelected = selectedSet[right.id] ? 0 : 1;
                    if (leftSelected !== rightSelected) {
                        return leftSelected - rightSelected;
                    }
                    if (leftSelected === 0) {
                        return selectedOrder.indexOf(left.id) - selectedOrder.indexOf(right.id);
                    }
                    return left.index - right.index;
                }).forEach(function (candidate) {
                    candidate.option.selected = Boolean(selectedSet[candidate.id]);
                    $source[0].appendChild(candidate.option);
                });
            }

            function filteredCandidates() {
                var query = normalize($search.val());
                var company = String($company.val() || '');
                return candidates.filter(function (candidate) {
                    var searchable = normalize([candidate.name, candidate.rut, candidate.email, candidate.company].join(' '));
                    return (!query || searchable.indexOf(query) !== -1) && (!company || candidate.company === company);
                });
            }

            function addCandidate(id) {
                id = String(id);
                if (!isSelected(id)) {
                    selectedOrder.push(id);
                }
            }

            function removeCandidate(id) {
                selectedOrder = selectedOrder.filter(function (selectedId) {
                    return selectedId !== String(id);
                });
            }

            function renderResults() {
                var visible = filteredCandidates();
                $results.empty();
                $empty.toggleClass('d-none', visible.length > 0);

                visible.forEach(function (candidate) {
                    var $label = $('<label>', { class: 'candidate-picker-option d-flex align-items-start gap-2 rounded p-2 mb-1' });
                    var $checkbox = $('<input>', { type: 'checkbox', class: 'form-check-input mt-1 flex-shrink-0', value: candidate.id, 'data-candidate-checkbox': true });
                    var $body = $('<span>', { class: 'min-w-0' });
                    $('<strong>', { class: 'd-block text-break' }).text(candidate.name || 'Sin nombre').appendTo($body);
                    $('<span>', { class: 'small text-muted d-block text-break' }).text([candidate.rut, candidate.email, candidate.company].filter(Boolean).join(' · ') || 'Sin datos adicionales').appendTo($body);
                    $checkbox.prop('checked', isSelected(candidate.id));
                    $label.append($checkbox, $body).appendTo($results);
                });
            }

            function renderSelected() {
                $selectedList.find('[data-candidate-selected-empty], [data-candidate-selected-row]').remove();
                $selectedEmpty.toggleClass('d-none', selectedOrder.length > 0);
                $count.text(selectedOrder.length + (selectedOrder.length === 1 ? ' seleccionado' : ' seleccionados'));

                selectedOrder.forEach(function (id, index) {
                    var candidate = candidates.find(function (item) { return item.id === id; });
                    if (!candidate) {
                        return;
                    }
                    var $row = $('<div>', { class: 'candidate-picker-selected-row d-flex align-items-start gap-2 py-2 border-bottom', 'data-candidate-selected-row': true });
                    var $position = $('<span>', { class: 'badge text-bg-secondary flex-shrink-0' }).text(index + 1);
                    var $body = $('<span>', { class: 'flex-grow-1 min-w-0' });
                    $('<strong>', { class: 'd-block text-break' }).text(candidate.name || 'Sin nombre').appendTo($body);
                    $('<span>', { class: 'small text-muted d-block text-break' }).text(candidate.rut || candidate.email || candidate.company || 'Sin datos adicionales').appendTo($body);
                    var $actions = $('<span>', { class: 'd-flex gap-1 flex-shrink-0' });
                    $('<button>', { type: 'button', class: 'btn btn-sm btn-outline-secondary', title: 'Subir', 'aria-label': 'Subir ' + candidate.name, 'data-candidate-move': 'up', 'data-candidate-id': id, disabled: index === 0 }).html('<i class="bi bi-chevron-up"></i>').appendTo($actions);
                    $('<button>', { type: 'button', class: 'btn btn-sm btn-outline-secondary', title: 'Bajar', 'aria-label': 'Bajar ' + candidate.name, 'data-candidate-move': 'down', 'data-candidate-id': id, disabled: index === selectedOrder.length - 1 }).html('<i class="bi bi-chevron-down"></i>').appendTo($actions);
                    $('<button>', { type: 'button', class: 'btn btn-sm btn-outline-danger', title: 'Quitar', 'aria-label': 'Quitar ' + candidate.name, 'data-candidate-remove': true, 'data-candidate-id': id }).html('<i class="bi bi-x-lg"></i>').appendTo($actions);
                    $row.append($position, $body, $actions).appendTo($selectedList);
                });
            }

            function render() {
                syncSourceOrder();
                renderResults();
                renderSelected();
            }

            $results.on('change', '[data-candidate-checkbox]', function () {
                if (this.checked) {
                    addCandidate(this.value);
                } else {
                    removeCandidate(this.value);
                }
                render();
            });

            $picker.on('click', '[data-candidate-select-visible]', function () {
                filteredCandidates().forEach(function (candidate) { addCandidate(candidate.id); });
                render();
            });

            $picker.on('click', '[data-candidate-clear]', function () {
                selectedOrder = [];
                render();
            });

            $picker.on('click', '[data-candidate-remove]', function () {
                removeCandidate($(this).data('candidate-id'));
                render();
            });

            $picker.on('click', '[data-candidate-move]', function () {
                var id = String($(this).data('candidate-id'));
                var currentIndex = selectedOrder.indexOf(id);
                var targetIndex = $(this).data('candidate-move') === 'up' ? currentIndex - 1 : currentIndex + 1;
                if (currentIndex < 0 || targetIndex < 0 || targetIndex >= selectedOrder.length) {
                    return;
                }
                var moved = selectedOrder.splice(currentIndex, 1)[0];
                selectedOrder.splice(targetIndex, 0, moved);
                render();
            });

            $search.add($company).on('input change', renderResults);
            render();
        });
    }

    $(initCandidatePicker);
}(jQuery));
