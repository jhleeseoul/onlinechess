document.addEventListener('DOMContentLoaded', () => {
    // ================== 인증 가드 및 기본 정보 ==================
    const token = localStorage.getItem('jwt_token');
    if (!token) {
        alert('로그인이 필요합니다.');
        window.location.href = './index.html';
        return;
    }
    const myUserInfo = JSON.parse(localStorage.getItem('user_info'));
    const urlParams = new URLSearchParams(window.location.search);
    const gameId = urlParams.get('gameId');

    if (!gameId) {
        alert('유효하지 않은 게임입니다.');
        window.location.href = './lobby.html';
        return;
    }

    // ================== DOM 요소 ==================
    const boardContainer = document.getElementById('board-container');
    // ... (나머지 DOM 요소는 다음 단계에서)

    // ================== 게임 상태 변수 ==================
    let gameState = null;
    let myColor = null;

    // ================== 함수 ==================

    /**
     * FEN을 기반으로 체스 보드 전체를 화면에 렌더링 (배경 + 말)
     */
    function renderBoard(fen, boardSkinPath, pieceSkinPath) {
        boardContainer.innerHTML = ''; // 컨테이너 초기화
        renderBoardBackground(boardSkinPath);
        renderPieces(fen, pieceSkinPath);
    }

    /**
     * 보드 배경 이미지를 렌더링
     */
    function renderBoardBackground(boardSkinPath) {
        const boardImg = document.createElement('img');
        boardImg.src = `${API_BASE_URL}${boardSkinPath}`;
        boardImg.className = 'board-background';
        boardContainer.appendChild(boardImg);
    }

    /**
     * FEN을 기반으로 말 이미지를 보드 위에 렌더링 (플레이어 시점 반영)
     */
    function renderPieces(fen, pieceSkinPath) {
        const fenParts = fen.split(' ')[0];
        const rows = fenParts.split('/');
        const squareSize = 60; // 480px / 8

        for (let r = 0; r < rows.length; r++) {
            let col = 0;
            for (let char of rows[r]) {
                if (!isNaN(char)) {
                    col += parseInt(char);
                } else {
                    // ======================= 수정된 부분 시작 =======================
                    let displayRow = r;
                    let displayCol = col;

                    // 내가 흑('b') 플레이어라면 보드를 뒤집어서 보여줌
                    if (myColor === 'b') {
                        displayRow = 7 - r;
                        displayCol = 7 - col;
                    }
                    
                    const pieceImg = document.createElement('img');
                    pieceImg.src = `${API_BASE_URL}${pieceSkinPath}${getPieceFileName(char)}.png`;
                    pieceImg.className = 'chess-piece';
                    // 계산된 display 좌표로 위치 설정
                    pieceImg.style.top = `${displayRow * squareSize}px`;
                    pieceImg.style.left = `${displayCol * squareSize}px`;
                    
                    // 데이터 속성에는 원래의 논리적 좌표를 저장 (중요!)
                    pieceImg.dataset.piece = char;
                    pieceImg.dataset.row = r;
                    pieceImg.dataset.col = col;
                    // ======================= 수정된 부분 끝 =======================

                    boardContainer.appendChild(pieceImg);
                    col++;
                }
            }
        }
    }

    /**
     * 말 문자(p, R, n)를 파일 이름(bP, wR, bN)으로 변환
     */
    function getPieceFileName(pieceChar) {
        const isWhite = pieceChar === pieceChar.toUpperCase();
        const colorPrefix = isWhite ? 'w' : 'b';
        const pieceType = pieceChar.toUpperCase();
        return `${colorPrefix}${pieceType}`;
    }


    /**
     * 게임 초기화 함수 (수정됨)
     */
    async function initializeGame() {
        try {
            const response = await request(`/api/game/${gameId}/status`, 'GET');
            gameState = response.game_data;
            
            const whitePlayer = response.white_player;
            const blackPlayer = response.black_player;

            myColor = (myUserInfo.id === whitePlayer.id) ? 'w' : 'b';

            // 플레이어 정보 UI 업데이트
            const myPlayerInfo = myColor === 'w' ? whitePlayer : blackPlayer;
            const opponentPlayerInfo = myColor === 'w' ? blackPlayer : whitePlayer;

            document.getElementById('my-icon').src = `${API_BASE_URL}${myPlayerInfo.profile_icon_path}`;
            document.getElementById('my-nickname').textContent = myPlayerInfo.nickname;
            document.getElementById('my-points').textContent = `(${myPlayerInfo.points}점)`;
            
            document.getElementById('opponent-icon').src = `${API_BASE_URL}${opponentPlayerInfo.profile_icon_path}`;
            document.getElementById('opponent-nickname').textContent = opponentPlayerInfo.nickname;
            document.getElementById('opponent-points').textContent = `(${opponentPlayerInfo.points}점)`;
            
            // 내 스킨 정보 가져오기
            const myBoardSkinPath = myPlayerInfo.board_skin_path;
            const myPieceSkinPath = myPlayerInfo.piece_skin_path;

            // 보드 렌더링 (스킨 경로 전달)
            renderBoard(gameState.fen, myBoardSkinPath, myPieceSkinPath);

            // TODO: 내 턴이면 게임 시작, 상대 턴이면 롱 폴링 시작
            
        } catch (error) {
            alert(`게임 정보를 불러오는 데 실패했습니다: ${error.message}`);
            window.location.href = './lobby.html';
        }
    }

    // ================== 초기 실행 ==================
    initializeGame();
});