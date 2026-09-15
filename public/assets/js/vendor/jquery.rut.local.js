(function ($) {
    'use strict';

    function clean(value) {
        return String(value || '').replace(/[^0-9kK]/g, '').toUpperCase().slice(0, 9);
    }

    function format(value) {
        var rut = clean(value);
        if (rut.length < 2) {
            return rut;
        }

        var body = rut.slice(0, -1);
        var dv = rut.slice(-1);
        var formatted = '';

        while (body.length > 3) {
            formatted = '.' + body.slice(-3) + formatted;
            body = body.slice(0, -3);
        }

        return body + formatted + '-' + dv;
    }

    function validate(value) {
        var rut = clean(value);
        if (!/^\d{7,8}[0-9K]$/.test(rut)) {
            return false;
        }

        var body = rut.slice(0, -1);
        var dv = rut.slice(-1);
        var factor = 2;
        var sum = 0;

        for (var index = body.length - 1; index >= 0; index--) {
            sum += parseInt(body.charAt(index), 10) * factor;
            factor = factor === 7 ? 2 : factor + 1;
        }

        var expected = 11 - (sum % 11);
        var expectedDv = expected === 11 ? '0' : (expected === 10 ? 'K' : String(expected));

        return dv === expectedDv;
    }

    $.Rut = {
        clean: clean,
        format: format,
        validate: validate
    };

    $.fn.rut = function () {
        return this.each(function () {
            var input = this;
            var $input = $(input);

            if ($input.data('rut-ready')) {
                return;
            }

            $input.data('rut-ready', true);
            $input.attr('maxlength', '12');

            $input.on('input blur', function () {
                var cursorAtEnd = input.selectionStart === input.value.length;
                input.value = format(input.value).slice(0, 12);
                if (cursorAtEnd && input.setSelectionRange) {
                    input.setSelectionRange(input.value.length, input.value.length);
                }

                if (input.value && !validate(input.value)) {
                    input.setCustomValidity('Ingresa un RUT valido.');
                } else {
                    input.setCustomValidity('');
                }
            });

            if (input.value) {
                input.value = format(input.value);
            }
        });
    };
})(jQuery);
