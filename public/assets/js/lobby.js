document.addEventListener('DOMContentLoaded', () => {
    // ================== 인증 가드 ==================
    const token = localStorage.getItem('jwt_token');
    if (!token) {
        alert('로그인이 필요합니다.');
        window.location.href = './index.html';
        return;
    }

    const userInfo = JSON.parse(localStorage.getItem('user_info'));
    
    // ================== DOM 요소 ==================
    const profileIcon = document.getElementById('profile-icon');
    const userNickname = document.getElementById('user-nickname');
    const userCoins = document.getElementById('user-coins');
    const userPoints = document.getElementById('user-points');
    const logoutButton = document.getElementById('logout-button');
    const navButtons = document.querySelectorAll('.lobby-nav button');
    const contentArea = document.getElementById('content-area');
    const views = document.querySelectorAll('.view');

    // ================== 초기화 함수 ==================
    function initializeLobby() {
        // 헤더 정보 채우기
        profileIcon.src = `${API_BASE_URL}${userInfo.profile_icon_path}`;
        userNickname.textContent = userInfo.nickname;
        userCoins.textContent = userInfo.coins;
        userPoints.textContent = userInfo.points;
    }

    // ================== 뷰 전환 함수 ==================
    function switchView(viewId) {
        views.forEach(view => {
            if (view.id === viewId) {
                view.classList.remove('hidden');
                loadViewContent(viewId); // 뷰에 맞는 콘텐츠 로드
            } else {
                view.classList.add('hidden');
            }
        });
    }

    // ================== 콘텐츠 로드 함수 (수정된 버전) ==================
    async function loadViewContent(viewId) {
        // 각 뷰의 콘텐츠를 담을 컨테이너를 직접 지정
        let contentContainer;

        try {
            switch (viewId) {
                case 'my-info-view':
                    // 내 정보는 전적과 인벤토리를 모두 로드해야 함
                    const matchesContainer = document.getElementById('my-matches-content');
                    const inventoryContainer = document.getElementById('my-inventory-content');
                    matchesContainer.innerHTML = '전적 로딩 중...';
                    inventoryContainer.innerHTML = '인벤토리 로딩 중...';

                    const matches = await request('/api/users/me/matches');
                    renderMyMatches(matches);

                    const inventory = await request('/api/users/me/items');
                    renderMyInventory(inventory);
                    break;
                
                case 'shop-view':
                    contentContainer = document.getElementById('shop-items-content');
                    contentContainer.innerHTML = '로딩 중...';
                    const items = await request('/api/shop/items');
                    renderShop(items);
                    break;
                
                case 'leaderboard-view':
                    contentContainer = document.getElementById('leaderboard-content');
                    contentContainer.innerHTML = '로딩 중...';
                    const leaderboardData = await request('/api/leaderboard');
                    renderLeaderboard(leaderboardData);
                    break;
            }
        } catch (error) {
            // 에러 발생 시 해당 컨테이너에 메시지 표시
            const errorContainer = document.querySelector(`#${viewId} > div`);
            if (errorContainer) {
                errorContainer.innerHTML = `<p class="error-text">${error.message}</p>`;
            }
        }
    }

    // ================== 렌더링 함수 (내 정보 렌더링 함수 추가) ==================
    function renderMyMatches(matches) {
        const container = document.getElementById('my-matches-content');
        if (matches.length === 0) {
            container.innerHTML = '<h3>게임 기록</h3><p>아직 플레이한 게임이 없습니다.</p>';
            return;
        }
        container.innerHTML = `
            <h3>게임 기록</h3>
            <table>
                <thead><tr><th>상대</th><th>내 색깔</th><th>결과</th><th>종료 사유</th><th>날짜</th></tr></thead>
                <tbody>
                    ${matches.map(m => `
                        <tr>
                            <td>${m.opponent_nickname}</td>
                            <td>${m.my_color}</td>
                            <td>${m.result}</td>
                            <td>${m.end_reason}</td>
                            <td>${new Date(m.start_at).toLocaleString()}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function renderMyInventory(items) {
        const container = document.getElementById('my-inventory-content');
        if (items.length === 0) {
            container.innerHTML = '<h3>보유 아이템</h3><p>보유한 아이템이 없습니다.</p>';
            return;
        }
        container.innerHTML = `
            <h3>보유 아이템</h3>
            <div class="inventory-grid">
                ${items.map(item => `
                    <div class="inventory-item">
                        <img src="${API_BASE_URL}${item.asset_path}" alt="${item.name}">
                        <p>${item.name}</p>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // ================== 렌더링 함수 ==================
    function renderShop(items) {
        const container = document.getElementById('shop-items-content');
        container.innerHTML = items.map(item => `
            <div class="shop-item">
                <img src="${API_BASE_URL}${item.asset_path}" alt="${item.name}" style="width: 50px;">
                <h3>${item.name}</h3>
                <p>${item.description}</p>
                <p>가격: ${item.price} 코인</p>
                <button class="buy-button" data-item-id="${item.id}">구매</button>
            </div>
        `).join('');
    }

    function renderLeaderboard(data) {
        const container = document.getElementById('leaderboard-content');
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>플레이어</th>
                        <th>점수</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.map(user => `
                        <tr>
                            <td>${user.rank}</td>
                            <td>
                                <img src="${API_BASE_URL}${user.profile_icon_path}" class="profile-icon" style="width: 24px; height: 24px;">
                                ${user.nickname}
                            </td>
                            <td>${user.points}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    // ================== 이벤트 리스너 ==================
    // 내비게이션 버튼 클릭
    navButtons.forEach(button => {
        button.addEventListener('click', () => {
            const viewId = button.getAttribute('data-view');
            switchView(viewId);
        });
    });

    // 로그아웃 버튼
    logoutButton.addEventListener('click', () => {
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('user_info');
        window.location.href = './index.html';
    });

    // ================== 초기 실행 ==================
    initializeLobby();
});