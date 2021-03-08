import '../sass/payment.scss';
import '../sass/payment/card.css';
import CardInfo from 'card-info';

(function($){
    $.fn.showPage = function(html, callback){
        return this.each(function(){
            let el = $(this);
            el.stop(true, true);
            let finish = {width: this.style.width, height: this.style.height};
            let cur = {width: el.width()+'px', height: el.height()+'px'};
            el.html(html);
            let next = {width: el.width()+'px', height: el.height()+'px'};
            el .css(cur)
                .animate(next, 500, function(){
                    el.css(finish); // restore initial style settings
                    if ( $.isFunction(callback) ) callback();
                });
        });
    };
})($);

const PAYMENT_METHOD_ID_YANDEX_CARD = 15;
const PAYMENT_METHOD_ID_MPAY_CARD = 37;

let isCheckingActive = false;
let continuePageContext = null;
let waitingPageContext = null;
$(document).ready(function() {
    $('.hide-large .status').css("display","none");
    $('.open_info_click').click(function() {
        $('.hide-large .status').slideToggle("slow");
        $('i', this ).toggleClass("fa-chevron-up fa-chevron-down");
        $('.mobile-menu').toggleClass("border-bottom-mobile-menu");
    });

    function checkPaymentStatusSetTimeout() {
        setTimeout(function(){checkPaymentStatus()}, 5000);
    }
    function checkPaymentStatus() {
        $.ajax('/payment/'+config.hash+'/status', {type: 'GET', success: function(data){
            waitingPageContext = data.waitingPageData;
            updateLoaderTopBox();
            if (data.statusId === 3) {
                showFormSubmitPage(data.formData, 'Оплачено<br>Переадресация в магазин');
            } else {
                checkPaymentStatusSetTimeout();
            }
        }, error: function(data){
            checkPaymentStatusSetTimeout();
        }});
    }

    let pages = {
        loader: function(){return $('.page-loader').html()},
        methods: function(){return $('.page-methods').html()},
        currency: function(){return $('.page-currency').html()},
        action: function(){return $('.page-action').html()},
        error: function(){return $('.page-error').html()},
        yandexCard: function(){return $('.page-yandexCard').html()},
        askEmail: function(){return $('.page-askEmail').html()},
        mobile: function(){return $('.page-mobile').html()},
        card: function(){return $('.page-card').html()},
    };

    function showErrorPage(header, text = '', showBackPayment = false) {
        $('.pageError .errorHeader').html(header);
        $('.pageError .errorText').html(text);
        if (true === showBackPayment) {
            $('.pageError .back_payment').css('display', 'block');
        }
        $('.page').showPage(pages.error);
    }
    function showActionPage(methodName, methodImg, content) {
        $('.pageActionImg')
            .attr('src', '/inc/img/paymentMethod/'+methodImg+'.png')
            .attr('title', methodName)
            .attr('alt', methodName);
        $('.pageActionMethodName').html(methodName);
        $('.page').showPage(pages.action);
        $('.page .pageActionProcessRight').html(content);
    }
    function showMobilePage(methodId, methodName, methodImg) {
        $('.pageActionImg')
            .attr('src', '/inc/img/paymentMethod/'+methodImg+'.png')
            .attr('title', methodName)
            .attr('alt', methodName);
        $('.mobile__form input[name=methodId]').attr('value', methodId);
        $('.pageActionMethodName').html(methodName);
        $('.page').showPage(pages.mobile);
    }
    function showLoaderPage(text = '', showBackPayment = false) {
        $('.pageLoaderText').html(text);
        if (true === showBackPayment) {
            $('.pageLoader .back_payment').css('display', 'block');
        }
        let page = $('.page');
        if (page.find('.pageLoader').length === 0) {
            page.showPage(pages.loader);
        }
    }
    function createActionForm(data, showButton, appendTo) {
        let form = $('<form>', {id: 'actionForm', action: data.action, method: data.method});
        for (let fieldName in data.fields) {
            form.append($('<input>', {name: fieldName, value: data.fields[fieldName], type: 'hidden'}));
        }
        if (true === showButton) {
            form.append($('<p class="continueButtonTooltip">Если переход не произойдет автоматически, нажмите кнопку продолжить</p><button class="continueButton">Продолжить</button>'));
        }
        form.appendTo(appendTo);

        return form;
    }
    function showFormSubmitPage(formData, message) {
        showLoaderPage(message);
        let form = createActionForm(formData, true, '.page .actionFormBox');
        setTimeout(function(){
            form.submit();
        }, 3000);
    }
    function showWaitingPage() {
        showLoaderPage('Ожидание подтверждения оплаты счета', true);
        updateLoaderTopBox();
        if (false === isCheckingActive) {
            isCheckingActive = true;
            checkPaymentStatus();
        }
    }
    function updateLoaderTopBox() {
        let type = waitingPageContext.type;
        let data = waitingPageContext.data;
        if (type === 'bitcoinAddress') {
            $('.page .pageLoaderTopBox').html('<img src="data:image/png;base64,'+data.qrCode+'"><br>'+data.address+'<br>'+data.amount+' btc');
        } else if (type === 'bitcoinConfirmations') {
            $('.page .pageLoaderTopBox').html('Мы уже видим ваш перевод и ожидаем достаточного количества подтверждений: '+data.confirmations+'/'+data.requiredConfirmations);
        } else if (type === 'mobile') {
            $('.page .pageLoaderTopBox').html('Следуйте инструкциям отправленным в смс на номер '+data.number);
        } else if (type === 'common') {
            $('.page .pageLoaderTopBox').html('');
        }
    }
    function showYandexCardPage(methodId, formData = null) {
        $('.page').showPage(pages.yandexCard);
        if (formData !== null) {
            createActionForm(formData, false, '.page');
        }
        CardInfo.setDefaultOptions({
            banksLogosPath: '/inc/cardForm/banks-logos/',
            brandsLogosPath: '/inc/cardForm/brands-logos/'
        });
        $('.page .yandexCard__input__code').mask('000');
        $('.page .yandexCard__methodId').attr('value', methodId);
    }
    function showAskEmailPage(methodName, methodImg, link) {
        $('.pageActionImg')
            .attr('src', '/inc/img/paymentMethod/'+methodImg+'.png')
            .attr('title', methodName)
            .attr('alt', methodName);
        $('.pageActionMethodName').html(methodName);
        $('.page').showPage(pages.askEmail);
    }
    $(document).on('keyup change paste', ".page .yandexCard__input__number", function(e) {
        let field = $(this);
        let bankLink = $('.yandexCard__bankLink');
        let brandLogo = $('.yandexCard__brandLogo');
        let cardInfo = new CardInfo(field.val());
        if (cardInfo.bankUrl) {
            bankLink
                .attr('href', cardInfo.bankUrl)
                .css('backgroundImage', 'url("' + cardInfo.bankLogo + '")')
                .show();
        } else {
            bankLink.hide();
        }
        $('.yandexCard__front')
            .css('background', cardInfo.backgroundGradient)
            .css('color', cardInfo.textColor);
        $('.yandexCard__input__code').attr('placeholder', cardInfo.codeName ? cardInfo.codeName : 'CVC');
        field.mask(cardInfo.numberMask);
        if (cardInfo.brandLogo) {
            brandLogo
                .attr('src', cardInfo.brandLogo)
                .attr('alt', cardInfo.brandName)
                .show();
        } else {
            brandLogo.hide();
        }
    }).trigger('keyup');
    $(document).on('input', '.page .yandexCard__form input', function() {
        $(this).removeClass('yandexCard__input__error');
    });
    $(document).on('change', '.page .yandexCard__form select', function() {
        $(this).removeClass('yandexCard__input__error');
    });
    $(document).on('submit', '.page .yandexCard__form', function(e) {
        let form = $(this);
        form.find('input').removeClass('yandexCard__input__error');
        let errors = [];
        let methodId = this.methodId.value-0;
        let cardNumber = this.cardNumber.value.replace(/\s/g, '');
        let cardHolder = this.cardHolder.value;
        let month = this.ccmonth.options[this.ccmonth.selectedIndex].value;
        let year = this.ccyear.options[this.ccyear.selectedIndex].value;
        if (cardNumber.length < 16) {
            errors.push(this.cardNumber);
        }
        if (cardHolder === '') {
            errors.push(this.cardHolder);
        }
        if (this.cvc.value.length < 3) {
            errors.push(this.cvc);
        }
        if (month === '0') {
            errors.push(this.ccmonth);
        }
        if (year === '0') {
            errors.push(this.ccyear);
        }

        if (errors.length > 0) {
            for (let i in errors) {
                $(errors[i]).addClass('yandexCard__input__error');
            }
        } else if (methodId === PAYMENT_METHOD_ID_MPAY_CARD) {
            let postData = {number: cardNumber, holder: cardHolder, year: year, month: month, cvc: this.cvc.value};
            showLoaderPage();
            $.ajax('/payment/'+config.hash+'/selfForm/'+methodId, {type: 'POST', data: postData, success: function(data){
                    continuePageContext = data;
                    showContinuePage();
                }.bind(this), error: function(data){
                    showErrorPage('Ошибка', 'Повторите попытку или обратитесь в поддержку', true);
                }.bind(this)
            });
        } else {
            let button = form.find('button');
            button.prop('disabled', true);

            let actionForm = $('#actionForm');
            let skrCardNumber = cardNumber;
            let skrYear = year.substring(2, 4);
            let skrMonth = month;
            let skrCvc = this.cvc.value;
            let skrFio = '';
            actionForm.append($('<input>', {name: 'skr_card-number', value: skrCardNumber, type: 'hidden'}));
            actionForm.append($('<input>', {name: 'skr_year', value: skrYear, type: 'hidden'}));
            actionForm.append($('<input>', {name: 'skr_month', value: skrMonth, type: 'hidden'}));
            actionForm.append($('<input>', {name: 'skr_cardCvc', value: skrCvc, type: 'hidden'}));
            actionForm.append($('<input>', {name: 'skr_fio', value: skrFio, type: 'hidden'}));
            actionForm.submit();
        }

        e.preventDefault();
    });
    $(document).on('submit', '.page .mobile__form', function(e) {
        let form = $(this);
        let number = this.number.value;
        let methodId = this.methodId.value;
        form.find('input').removeClass('mobile__input__error');
        if (number.match(/^79\d{9}$/) === null) {
            $(this.number).addClass('mobile__input__error');
        } else {
            let postData = {number: number};
            showLoaderPage();
            $.ajax('/payment/'+config.hash+'/selfForm/'+methodId, {type: 'POST', data: postData, success: function(data){
                waitingPageContext = data.waitingPageData;
                showWaitingPage();
            }, error: function(data){
                showErrorPage('Ошибка', 'Повторите попытку или обратитесь в поддержку', true);
            }});
        }

        e.preventDefault();
    });
    function showContinuePage() {
        let context = continuePageContext;
        /*if (context.askEmail) {
            showAskEmailPage(context.methodName, context.methodImg, context.linkUrl);
        } else */if (context.actionType === 'form') {
            if (context.form.customFormName === 'yandexCard') {
                showYandexCardPage(PAYMENT_METHOD_ID_YANDEX_CARD, context.form);
            } else if (true === context.isNeedToWaitingPage) {
                showActionPage(context.methodName, context.methodImg, '');
                let form = createActionForm(context.form, false, '.page .pageActionProcessRight');
                form.attr('target', '_blank');
                form.append($('<button class="payment_process_right_pay_button pageActionLink"><i class="fas fa-share-square"></i>Оплатить</button>'));
            } else {
                showFormSubmitPage(context.form, 'Переадресация на оплату');
            }
        } else if (context.actionType === 'link' && true === context.isNeedToWaitingPage) {
            let actionContent = '<a href="'+context.linkUrl+'" target="_blank" class="payment_process_right_pay_button pageActionLink"><i class="fas fa-share-square"></i>Оплатить</a>';
            showActionPage(context.methodName, context.methodImg, actionContent);
        } else if (context.actionType === 'link' && false === context.isNeedToWaitingPage) {
            let form = {method: 'GET', action: context.linkUrl, fields: []};
            showFormSubmitPage(form, 'Переадресация на оплату');
        } else if (context.actionType === 'specialWaitingPage') {
            showWaitingPage();
        } else if (context.actionType === 'specialHtml') {
            showActionPage(context.methodName, context.methodImg, context.html);
        } else if (context.actionType === 'selfForm' && context.selfFormType === 'mobile') {
            showMobilePage(context.methodId, context.methodName, context.methodImg);
        } else if (context.actionType === 'selfForm' && context.selfFormType === 'card') {
            showYandexCardPage(PAYMENT_METHOD_ID_MPAY_CARD);
        } else {
            showErrorPage('Ошибка', 'Повторите попытку позже или обратитесь в поддержку', true);
        }
    }

    waitingPageContext = config.waitingPageData;
    if (config.startPage === 'methods') {
        $('.page').showPage(pages.methods);
    } else if (config.startPage === 'waiting') {
        showWaitingPage();
    } else if (config.startPage === 'dead') {
        showErrorPage('Ошибка', config.error);
    } else if (config.startPage === 'wrong') {
        showErrorPage('Ошибка', config.error, true);
        if (config.payment.paymentMethodId !== null) {
            let method = config.methods[config.payment.paymentMethodId];
            if (method !== undefined && method.alternativeId !== null) {
                let alternativeMethod = config.methods[method.alternativeId];
                if (alternativeMethod !== undefined) {
                    $('.page .paymentMethod')
                        .css('display', 'inline')
                        .data('id', alternativeMethod.id)
                        .data('img', '/inc/img/paymentMethod/' + alternativeMethod.img + '.png')
                        .data('name', alternativeMethod.name);
                }
            }

        }
    } else if (config.startPage === 'redirect') {
        showFormSubmitPage(config.redirectFormData, 'Оплачено<br>Переадресация в магазин');
    } else if (config.startPage === 'continue') {
        continuePageContext = config.continuePageData;
        showContinuePage();
    }


    $(document).on('click', '.back_payment', function() {
        $('.page').showPage(pages.methods);
    });

    $(document).on('click', '.pageActionLink', function() {
        showWaitingPage();
    });

    function updateHelpFormCaptcha() {
        $('.helpForm__captcha').html('<img src="/captcha?'+Math.random()+'" id="captchaImage">');
    }
    $(document).on('submit', '.askEmail__form', function(e) {
        let form = $(this);
        let input = this.email;
        let button = form.find('button');
        let errorField = form.find('.askEmail__error');
        button.prop('disabled', true);
        errorField.html('');

        $.ajax({type: "POST", url: '/payment/'+config.hash+'/setEmail', data: form.serialize(), success: function(data){
                button.prop('disabled', false);
                continuePageContext.askEmail = false;
                showContinuePage();
            }, error: function (data) {
                button.prop('disabled', false);
                if (data.responseJSON !== undefined) {
                    errorField.html('Неверный формат email');
                } else {
                    errorField.html('Ошибка. Попробуйте еще раз');
                }
            }
        });
        e.preventDefault();
    });
    $(document).on('submit', '.helpForm form', function(e) {
        let form = $(this);
        let button = form.find('button');
        button.prop('disabled', true);
        form.find('.helpFormError').html('');

        $.ajax({type: "POST", url: form.attr('action'), data: form.serialize(), success: function(data){
                updateHelpFormCaptcha();
                button.prop('disabled', false);
                $('.helpForm').html('Сообщение успешно отправлено, ожидайте ответа');
            }, error: function (data) {
                updateHelpFormCaptcha();
                button.prop('disabled', false);
                if (data.responseJSON !== undefined) {
                    let errors = data.responseJSON.errors;
                    for (let key in errors) {
                        form.find('.helpFormError__'+key).html(errors[key]);
                    }
                } else {
                    form.find('.helpFormError__form').html('Ошибка. Попробуйте позже');
                }
            }
        });
        e.preventDefault();
    });

    $(document).on('click', '.paymentMethod', function() {
        let el = $(this);
        let id = el.data('id');
        let img = el.data('img');
        let name = el.data('name');
        let methods = el.data('methods');
        if (el.data('is-group') === 1) {
            $('.pageCurrencyImg')
                .attr('src', img)
                .attr('title', name)
                .attr('alt', name);
            $('.pageCurrencyMethodName').html(name);

            $('.pageCurrencyMethods').html('');
            methods.forEach(function(method) {
                $('.pageCurrencyMethods').append('&nbsp;<button class="payment_process_right_choose_button paymentMethod" data-id="'+method.id+'">'+method.currencyShortLatin+'</button>');
            });

            $('.page').showPage(pages.currency);
        } else {
            showLoaderPage();
            $.ajax('/payment/'+config.hash+'/selectMethod/'+id, {type: 'POST', success: function(data){
                continuePageContext = data;
                waitingPageContext = data.waitingPageData;
                showContinuePage();
            }, error: function(data){
                if (undefined === data.responseJSON) {
                    showErrorPage('Ошибка', 'Повторите попытку позже или обратитесь в поддержку');
                } else {
                    if (true === data.responseJSON.isPaymentFound) {
                        showErrorPage('Ошибка', data.responseJSON.error, true);
                    } else {
                        showErrorPage('Ошибка', data.responseJSON.error);
                    }
                }
            }});
        }
    });
    $('#help_popup').popup({
        transition: 'all 0.3s',
        scrolllock: true,
        onopen: function () {
            updateHelpFormCaptcha();
        },
    });
});
