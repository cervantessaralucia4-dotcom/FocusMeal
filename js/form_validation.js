document.addEventListener('DOMContentLoaded', function () {
	const forms = document.querySelectorAll('form.validate-form');
	if (!forms || forms.length === 0) return;

	forms.forEach(form => {
		form.addEventListener('submit', function (e) {
			e.stopPropagation();

			// Validar contraseñas coincidan
			const password = form.querySelector('#contraseña');
			const passwordConfirm = form.querySelector('#passwordConfirm');
			if (password && passwordConfirm) {
				passwordConfirm.setCustomValidity('');
				if (password.value !== passwordConfirm.value) {
					passwordConfirm.setCustomValidity('Las contraseñas no coinciden');
				}
			}

			// Mostrar validaciones visuales
			if (!form.checkValidity()) {
				form.classList.add('was-validated');
				Array.from(form.elements).forEach(field => {
					if (field.classList && field.classList.contains('form-control')) {
						if (field.checkValidity()) {
							field.classList.remove('is-invalid');
							field.classList.add('is-valid');
						} else {
							field.classList.remove('is-valid');
							field.classList.add('is-invalid');
						}
					}
				});
				e.preventDefault();
				return;
			}

			// ✅ Si todo está bien, enviar realmente el formulario al PHP
			form.submit();
		});

		// Validación en tiempo real
		Array.from(form.querySelectorAll('.form-control')).forEach(f => {
			f.addEventListener('input', function () {
				if (f.checkValidity()) {
					f.classList.add('is-valid');
					f.classList.remove('is-invalid');
				} else {
					f.classList.add('is-invalid');
					f.classList.remove('is-valid');
				}

				// Validar coincidencia de contraseñas mientras escribe
				if (f.id === 'passwordConfirm') {
					const pw = form.querySelector('#contraseña');
					if (pw && f.value !== pw.value) {
						f.setCustomValidity('Las contraseñas no coinciden');
					} else {
						f.setCustomValidity('');
					}
				}
			});
		});
	});
});
