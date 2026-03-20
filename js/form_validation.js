document.addEventListener('DOMContentLoaded', function () {

    // ── REGISTRO ──────────────────────────────────────────
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {

        const nombre        = registerForm.querySelector('#nombre');
        const correo        = registerForm.querySelector('#correo');
        const password      = registerForm.querySelector('#password');
        const passwordConf  = registerForm.querySelector('#passwordConfirm');
        const edad          = registerForm.querySelector('#edad');
        const peso          = registerForm.querySelector('#peso_actual');
        const altura        = registerForm.querySelector('#altura');

        // Helpers
        function setValid(field) {
            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
        }
        function setInvalid(field) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
        }
        function clearState(field) {
            field.classList.remove('is-valid', 'is-invalid');
        }

        // Reglas de validación por campo
        function validateNombre() {
            if (!nombre) return true;
            const ok = nombre.value.trim().length >= 2;
            ok ? setValid(nombre) : setInvalid(nombre);
            return ok;
        }

        function validateCorreo() {
            if (!correo) return true;
            const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.value.trim());
            ok ? setValid(correo) : setInvalid(correo);
            return ok;
        }

        function validatePassword() {
            if (!password) return true;
            const ok = password.value.length >= 8;
            ok ? setValid(password) : setInvalid(password);
            // Re-validar confirmación si ya tiene algo escrito
            if (passwordConf && passwordConf.value.length > 0) validatePasswordConf();
            return ok;
        }

        function validatePasswordConf() {
            if (!passwordConf || !password) return true;
            const ok = passwordConf.value === password.value && passwordConf.value.length >= 8;
            passwordConf.setCustomValidity(ok ? '' : 'no coincide');
            ok ? setValid(passwordConf) : setInvalid(passwordConf);
            return ok;
        }

        function validateEdad() {
            if (!edad || edad.value === '') { clearState(edad); return true; }
            const v = parseInt(edad.value);
            const ok = v >= 10 && v <= 120;
            ok ? setValid(edad) : setInvalid(edad);
            return ok;
        }

        function validatePeso() {
            if (!peso || peso.value === '') { clearState(peso); return true; }
            const v = parseFloat(peso.value);
            const ok = v > 0 && v <= 500;
            ok ? setValid(peso) : setInvalid(peso);
            return ok;
        }

        function validateAltura() {
            if (!altura || altura.value === '') { clearState(altura); return true; }
            const v = parseFloat(altura.value);
            const ok = v > 0 && v <= 300;
            ok ? setValid(altura) : setInvalid(altura);
            return ok;
        }

        // Validación en tiempo real
        if (nombre)       nombre.addEventListener('input', validateNombre);
        if (correo)       correo.addEventListener('input', validateCorreo);
        if (password)     password.addEventListener('input', validatePassword);
        if (passwordConf) passwordConf.addEventListener('input', validatePasswordConf);
        if (edad)         edad.addEventListener('input', validateEdad);
        if (peso)         peso.addEventListener('input', validatePeso);
        if (altura)       altura.addEventListener('input', validateAltura);

        // Al enviar
        registerForm.addEventListener('submit', function (e) {
            const ok = [
                validateNombre(),
                validateCorreo(),
                validatePassword(),
                validatePasswordConf(),
                validateEdad(),
                validatePeso(),
                validateAltura()
            ].every(Boolean);

            if (!ok) {
                e.preventDefault();
                // Scroll al primer campo inválido
                const primero = registerForm.querySelector('.is-invalid');
                if (primero) primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    // ── LOGIN ─────────────────────────────────────────────
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {

        const correoLogin = loginForm.querySelector('#email');
        const passLogin   = loginForm.querySelector('#password');

        function validateCorreoLogin() {
            if (!correoLogin) return true;
            const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correoLogin.value.trim());
            ok ? correoLogin.classList.add('is-valid') : correoLogin.classList.add('is-invalid');
            ok ? correoLogin.classList.remove('is-invalid') : correoLogin.classList.remove('is-valid');
            return ok;
        }

        function validatePassLogin() {
            if (!passLogin) return true;
            const ok = passLogin.value.length >= 8;
            ok ? passLogin.classList.add('is-valid') : passLogin.classList.add('is-invalid');
            ok ? passLogin.classList.remove('is-invalid') : passLogin.classList.remove('is-valid');
            return ok;
        }

        if (correoLogin) correoLogin.addEventListener('input', validateCorreoLogin);
        if (passLogin)   passLogin.addEventListener('input', validatePassLogin);

        loginForm.addEventListener('submit', function (e) {
            const ok = [validateCorreoLogin(), validatePassLogin()].every(Boolean);
            if (!ok) e.preventDefault();
        });
    }

});