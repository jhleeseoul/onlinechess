document.addEventListener('DOMContentLoaded', () => {
    // DOM 요소 가져오기
    const loginFormContainer = document.getElementById('login-form-container');
    const registerFormContainer = document.getElementById('register-form-container');
    const showRegisterLink = document.getElementById('show-register-link');
    const showLoginLink = document.getElementById('show-login-link');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const errorMessage = document.getElementById('error-message');

    // 폼 전환 링크 이벤트 리스너
    showRegisterLink.addEventListener('click', (e) => {
        e.preventDefault();
        loginFormContainer.classList.add('hidden');
        registerFormContainer.classList.remove('hidden');
        errorMessage.textContent = '';
    });

    showLoginLink.addEventListener('click', (e) => {
        e.preventDefault();
        registerFormContainer.classList.add('hidden');
        loginFormContainer.classList.remove('hidden');
        errorMessage.textContent = '';
    });

    // 회원가입 폼 제출 이벤트
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('register-username').value;
        const nickname = document.getElementById('register-nickname').value;
        const password = document.getElementById('register-password').value;
        
        try {
            const data = await request('/api/users', 'POST', { username, nickname, password });
            alert(data.message); // "회원가입이 완료되었습니다."
            // 회원가입 성공 후 로그인 폼으로 전환
            showLoginLink.click();
        } catch (error) {
            errorMessage.textContent = error.message;
        }
    });

    // 로그인 폼 제출 이벤트
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;

        try {
            // 1. 로그인 API 호출
            const loginData = await request('/api/auth/login', 'POST', { username, password });
            const token = loginData.token;

            // 2. 토큰을 localStorage에 저장
            localStorage.setItem('jwt_token', token);

            // 3. 내 정보 API를 호출하여 유저 정보 가져오기
            const userData = await request('/api/users/me', 'GET');

            // 4. 유저 정보를 localStorage에 저장
            localStorage.setItem('user_info', JSON.stringify(userData));

            // 5. 로비 페이지로 리디렉션
            window.location.href = './lobby.html';

        } catch (error) {
            errorMessage.textContent = error.message;
        }
    });
});