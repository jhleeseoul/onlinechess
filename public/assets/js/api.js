// API 서버의 기본 URL. 환경에 따라 변경해야 할 수 있습니다.
const API_BASE_URL = 'http://localhost/onlinechess/public';

/**
 * API 요청을 보내는 범용 함수
 * @param {string} endpoint API 엔드포인트 (예: '/api/users/me')
 * @param {string} method HTTP 메소드 (GET, POST, PATCH 등)
 * @param {object|null} body 요청 본문에 담을 데이터 (GET 요청 시에는 null)
 * @returns {Promise<object>} 서버로부터 받은 JSON 데이터
 */
async function request(endpoint, method = 'GET', body = null) {
    const url = `${API_BASE_URL}${endpoint}`;
    const token = localStorage.getItem('jwt_token');

    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        },
    };

    if (token) {
        options.headers['Authorization'] = `Bearer ${token}`;
    }

    if (body) {
        options.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, options);
        
        // 응답이 비어있는 경우 (예: 204 No Content)를 대비
        const responseText = await response.text();
        const data = responseText ? JSON.parse(responseText) : {};

        if (!response.ok) {
            // 서버에서 보낸 에러 메시지를 포함하여 에러를 발생시킴
            const errorMessage = data.message || `HTTP error! status: ${response.status}`;
            throw new Error(errorMessage);
        }

        return data;
    } catch (error) {
        console.error('API request error:', error);
        // 에러를 다시 던져서 호출한 쪽에서 처리할 수 있도록 함
        throw error;
    }
}