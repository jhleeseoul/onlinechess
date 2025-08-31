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
    const startMatchmakingButton = document.getElementById('start-matchmaking-button');
    const matchmakingStatus = document.getElementById('matchmaking-status');

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
            <div class="scrollable-table">
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
            </div>
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
            <div class="horizontal-scroll-grid">
                ${items.map(item => `
                    <div class="grid-item">
                        <img src="${API_BASE_URL}${item.asset_path}${item.item_type === 'piece_skin' ? 'wK.png' : ''}" alt="${item.name}">
                        <p>${item.name}</p>
                        <button class="equip-button" data-user-item-id="${item.user_item_id}">장착</button>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // ================== 렌더링 함수 ==================
    function renderShop(items) {
        const container = document.getElementById('shop-items-content');
        container.innerHTML = `
            <div class="shop-grid">
                ${items.map(item => `
                    <div class="shop-item">
                        <img src="${API_BASE_URL}${item.asset_path}${item.item_type === 'piece_skin' ? 'wK.png' : ''}" alt="${item.name}">
                        <h3>${item.name}</h3>
                        <p>${item.description}</p>
                        <p>가격: ${item.price} 코인</p>
                        <button class="buy-button" data-item-id="${item.id}">구매</button>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderLeaderboard(data) {
        const container = document.getElementById('leaderboard-content');
        container.innerHTML = `
        <div class="scrollable-table">
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
        </div>
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

    // 매치메이킹 시작 버튼 클릭
    startMatchmakingButton.addEventListener('click', startMatchmaking);
    
    // 콘텐츠 영역의 클릭 이벤트를 위임하여 처리
    contentArea.addEventListener('click', async (event) => {
        // 구매 버튼 클릭 시
        if (event.target.classList.contains('buy-button')) {
            const button = event.target;
            const itemId = button.dataset.itemId;
            
            if (confirm(`'${button.parentElement.querySelector('h3').textContent}' 아이템을 구매하시겠습니까?`)) {
                try {
                    button.disabled = true;
                    button.textContent = '처리 중...';
                    const response = await request(`/api/shop/items/${itemId}/buy`, 'POST');
                    alert(response.message);
                    // 구매 성공 시, 내 정보(코인)와 인벤토리 뷰를 새로고침
                    updateUserInfo();
                    if (document.getElementById('my-info-view').classList.contains('hidden') === false) {
                        loadViewContent('my-info-view');
                    }
                } catch (error) {
                    alert(`구매 실패: ${error.message}`);
                } finally {
                    button.disabled = false;
                    button.textContent = '구매';
                }
            }
        }

        // 장착 버튼 클릭 시
        if (event.target.classList.contains('equip-button')) {
            const button = event.target;
            const userItemId = button.dataset.userItemId;
            
            if (confirm(`'${button.parentElement.querySelector('p').textContent}' 아이템을 장착하시겠습니까?`)) {
                try {
                    button.disabled = true;
                    button.textContent = '장착 중...';
                    const response = await request(`/api/users/me/items/${userItemId}/equip`, 'POST');
                    alert(response.message);
                    // 장착 성공 시, 헤더의 아이콘 등 내 정보를 새로고침
                    updateUserInfo(); 
                } catch (error) {
                    alert(`장착 실패: ${error.message}`);
                } finally {
                    button.disabled = false;
                    button.textContent = '장착';
                }
            }
        }
    });
    
    // ================== 추가 헬퍼 함수 ==================
    // 내 정보를 서버에서 새로고침하고 localStorage와 헤더 UI를 업데이트하는 함수
    async function updateUserInfo() {
        try {
            const userData = await request('/api/users/me', 'GET');
            localStorage.setItem('user_info', JSON.stringify(userData));
            // 헤더 UI에도 즉시 반영
            initializeLobby(); 
        } catch (error) {
            console.error('Failed to update user info:', error);
        }
    }

    // ================== 매치메이킹 관련 함수 ==================
    let isMatchmaking = false;

    async function startMatchmaking() {
        if (isMatchmaking) return;
        isMatchmaking = true;

        startMatchmakingButton.disabled = true;
        matchmakingStatus.innerHTML = '<p>상대방을 찾는 중입니다...</p>';

        try {
            const response = await request('/api/match/rank', 'POST');

            if (response.status === 'matched') {
                handleMatchSuccess(response);
            } else if (response.status === 'pending') {
                await waitForMatch();
            }
        } catch (error) {
            matchmakingStatus.innerHTML = `<p class="error-text">매칭 중 오류 발생: ${error.message}</p>`;
            resetMatchmakingUI();
        }
    }

    async function waitForMatch() {
        try {
            const response = await request('/api/match/wait', 'GET');
            
            if (response.status === 'matched') {
                handleMatchSuccess(response);
            } else if (response.status === 'pending') {
                // 타임아웃 발생 시, 다시 대기 요청
                await waitForMatch();
            }
        } catch (error) {
            matchmakingStatus.innerHTML = `<p class="error-text">매칭 대기 중 오류 발생: ${error.message}</p>`;
            resetMatchmakingUI();
        }
    }

    function handleMatchSuccess(matchData) {
        const opponent = matchData.opponent;
        matchmakingStatus.innerHTML = `
            <div class="opponent-info">
                <h3>매치 성공!</h3>
                <img src="${API_BASE_URL}${opponent.profile_icon_path}" alt="상대 아이콘">
                <p><strong>${opponent.nickname}</strong> (${opponent.points}점) 님과 대결합니다.</p>
                <p>잠시 후 게임을 시작합니다... <span id="countdown">5</span></p>
            </div>
        `;

        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        const interval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            if (countdown === 0) {
                clearInterval(interval);
                window.location.href = `./game.html?gameId=${matchData.game_id}`;
            }
        }, 1000);
    }

    function resetMatchmakingUI() {
        isMatchmaking = false;
        startMatchmakingButton.disabled = false;
    }
    // ================== 초기 실행 ==================
    initializeLobby();
});