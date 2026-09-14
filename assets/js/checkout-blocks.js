(function () {
	'use strict';

	const settings = window.wc.wcSettings.getSetting('daraja_mpesa_data', {});
	const { createElement, useEffect, useState } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const title = decodeEntities(settings.title || 'M-Pesa');
	const description = decodeEntities(
		settings.description ||
			'Pay securely through an M-Pesa prompt on your phone.'
	);
	const phonePattern = /^(?:\+?254|0)(?:7|1)\d{8}$/;

	const Label = () =>
		createElement(
			'span',
			{ className: 'daraja-mpesa-blocks-label' },
			title
		);

	const Content = ({ eventRegistration, emitResponse }) => {
		const [phone, setPhone] = useState('');
		const { onPaymentSetup } = eventRegistration;

		useEffect(() => {
			const unsubscribe = onPaymentSetup(() => {
				if (!phonePattern.test(phone.replace(/\s+/g, ''))) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message:
							'Enter a valid Safaricom number such as 0712345678.',
					};
				}

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							daraja_mpesa_phone: phone,
						},
					},
				};
			});

			return unsubscribe;
		}, [emitResponse.responseTypes, onPaymentSetup, phone]);

		return createElement(
			'div',
			{ className: 'daraja-mpesa-blocks-content' },
			createElement('p', null, description),
			createElement(
				'label',
				{ htmlFor: 'daraja-mpesa-blocks-phone' },
				'M-Pesa phone number'
			),
			createElement('input', {
				id: 'daraja-mpesa-blocks-phone',
				type: 'tel',
				autoComplete: 'tel',
				placeholder: '07XXXXXXXX',
				required: true,
				value: phone,
				onChange: (event) => setPhone(event.target.value),
			})
		);
	};

	registerPaymentMethod({
		name: 'daraja_mpesa',
		label: createElement(Label),
		content: createElement(Content),
		edit: createElement(Content),
		canMakePayment: () => true,
		ariaLabel: title,
		supports: {
			features: settings.supports || ['products'],
		},
	});
})();
