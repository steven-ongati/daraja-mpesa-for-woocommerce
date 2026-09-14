(function () {
	'use strict';

	const maximumRefreshes = 60;

	document
		.querySelectorAll('.daraja-mpesa-customer-status')
		.forEach((container) => {
			const message = container.querySelector('[data-daraja-status]');
			const endpoint = container.dataset.statusUrl;

			if (!message || !endpoint) {
				return;
			}

			const button = container.querySelector('[data-daraja-refresh]');
			let refreshes = 0;
			let timer;

			const refresh = async () => {
				if (refreshes >= maximumRefreshes) {
					return;
				}

				refreshes += 1;
				if (button) {
					button.disabled = true;
				}

				try {
					const response = await window.fetch(endpoint, {
						credentials: 'same-origin',
						headers: { Accept: 'application/json' },
					});
					if (!response.ok) {
						return;
					}

					const status = await response.json();
					if (
						typeof status.message !== 'string' ||
						typeof status.code !== 'string'
					) {
						return;
					}

					message.textContent = status.message;
					message.dataset.darajaStatus = status.code;

					if (status.refreshable && refreshes < maximumRefreshes) {
						timer = window.setTimeout(refresh, 5000);
					} else if (button) {
						button.remove();
					}
				} catch {
					return;
				} finally {
					if (button && button.isConnected) {
						button.disabled = false;
					}
				}
			};

			if (button) {
				button.addEventListener('click', () => {
					window.clearTimeout(timer);
					refresh();
				});
			}

			timer = window.setTimeout(refresh, 5000);
		});
})();
