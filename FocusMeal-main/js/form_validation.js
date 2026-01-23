document.addEventListener('DOMContentLoaded', function () {
	const forms = document.querySelectorAll('form.validate-form');
	if (!forms || forms.length === 0) return;

	forms.forEach(form => {
		const password = form.querySelector('#password');
		const passwordConfirm = form.querySelector('#passwordConfirm');
		const passwordMatch = form.querySelector('#passwordMatch');

		// Función para actualizar el mensaje de coincidencia
		function updatePasswordMatch() {
			if (!password || !passwordConfirm || !passwordMatch) return;

			if (passwordConfirm.value === '') {
				passwordMatch.style.display = 'none';
				return;
			}

			passwordMatch.style.display = 'block';

			if (password.value === passwordConfirm.value && password.value !== '') {
				passwordMatch.innerHTML = '<i class="bi bi-check-circle-fill" style="color: #28a745;"></i> <span style="color: #28a745;"><strong>✓ Las contraseñas coinciden</strong></span>';
				passwordConfirm.classList.remove('is-invalid');
				passwordConfirm.classList.add('is-valid');
				passwordConfirm.setCustomValidity('');
			} else {
				passwordMatch.innerHTML = '<i class="bi bi-x-circle-fill" style="color: #dc3545;"></i> <span style="color: #dc3545;"><strong>✗ Las contraseñas no coinciden</strong></span>';
				passwordConfirm.classList.remove('is-valid');
				passwordConfirm.classList.add('is-invalid');
				passwordConfirm.setCustomValidity('Las contraseñas no coinciden');
			}
		}

		// Agregar listeners a los campos de contraseña
		if (password) {
			password.addEventListener('input', updatePasswordMatch);
		}
		if (passwordConfirm) {
			passwordConfirm.addEventListener('input', updatePasswordMatch);
		}

		form.addEventListener('submit', function (e) {
			e.stopPropagation();

			// Validar contraseñas coincidan
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

		// Validación en tiempo real para otros campos
		Array.from(form.querySelectorAll('.form-control')).forEach(f => {
			if (f.id === 'password' || f.id === 'passwordConfirm') return;

			f.addEventListener('input', function () {
				if (f.checkValidity()) {
					f.classList.add('is-valid');
					f.classList.remove('is-invalid');
				} else {
					f.classList.add('is-invalid');
					f.classList.remove('is-valid');
				}
			});
		});
	});
});
