(function (r) {
    if (typeof exports === "object" && typeof module !== "undefined") {
        module.exports = r()
    } else if (typeof define === "function" && define.amd) {
        define([], r)
    } else {
        var e;
        if (typeof window !== "undefined") {
            e = window
        } else if (typeof global !== "undefined") {
            e = global
        } else if (typeof self !== "undefined") {
            e = self
        } else {
            e = this
        }
        e.main = r()
    }
}(function () {
    "use strict";

    const TIMEZONE_DEFAULT_FORMAT = 'd-m H:i';
    const TIMEZONE_TIME_FORMAT = 'H:i';
    const TIMEZONE_DETAILS_FORMAT = 'Y-m-d H:i';

    return {
        TIMEZONE_DEFAULT_FORMAT: TIMEZONE_DEFAULT_FORMAT,
        TIMEZONE_TIME_FORMAT: TIMEZONE_TIME_FORMAT,
        TIMEZONE_DETAILS_FORMAT: TIMEZONE_DETAILS_FORMAT,
        request: function (http, snack, method, url, form, func, errorFunc = () => {}) {
            if (method === 'get') {
                http.get(url).then((response) => {
                    func(response);
                }).catch((response) => {
                    snack.danger({text: 'Ошибка ' + response.status, button: 'закрыть'});
                    errorFunc();
                });
            } else {
                form.errors = {};
                let post = {'_token': config.token};
                for (let item in form.data) {
                    let name = 'form[' + item + ']';
                    post[name] = form.data[item];
                }
                http.post(url, post, {emulateJSON: true}).then((response) => {
                    form.submitting = false;
                    func(response);
                }).catch((response) => {
                    if (response.status !== 400) {
                        snack.danger({text: 'Ошибка ' + response.status, button: 'закрыть'});
                    }
                    let errors = response.body.errors;
                    form.submitting = false;
                    form.errors = errors;
                    errorFunc(errors);
                });
            }
        },
        getItemByProperty: function (items, key, value) {
            for (let item of items) {
                if (item[key] === value) {
                    return item;
                }
            }

            return null;
        },
        convertTimeZone: function (date, format = TIMEZONE_DEFAULT_FORMAT) {
            let dtFormat = function (date, format) {
                let addZero = function (v) {
                    return ('00' + v).slice(-2);
                };
                let set = {
                    'Y': date.getFullYear(),
                    'm': addZero(date.getMonth() + 1),
                    'd': addZero(date.getDate()),
                    'H': addZero(date.getHours()),
                    'i': addZero(date.getMinutes()),
                };
                let formatted = '';
                for (let i = 0; i < format.length; i++) {
                    let sign = format[i];
                    if (set[sign] !== undefined) {
                        formatted += set[sign];
                    } else {
                        formatted += sign;
                    }
                }

                return formatted;
            };
            let dt = new timezoneJs.Date(date, 'Atlantic/Reykjavik');
            dt.setTimezone(config.userSettings.timezone);

            return dtFormat(dt, format);
        },
        formatMoney: function (value) {
            return value.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        },
        initForm: function (data) {
            return {
                data: data,
                errors: {},
                submitting: false,
                dialog: false,
            };
        },
    };
}));
