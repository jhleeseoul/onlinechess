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
    const gameStatusMessage = document.getElementById('game-status-message');
    // ... (나머지 DOM 요소는 다음 단계에서)

    // ================== 게임 상태 변수 ==================
    let gameState = null;
    let myColor = null;
    let selectedPiece = null; // 현재 선택된 말 DOM 요소
    let validMoves = [];      // 선택된 말의 유효한 이동 경로 배열

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
     * 유효한 이동 경로를 보드 위에 시각적으로 표시
     */
    function showValidMoves(moves) {
        clearHighlights(); // 기존 하이라이트 제거
        moves.forEach(move => {
            const index = coordToIndex(move);
            if (index) {
                const highlight = document.createElement('div');
                highlight.classList.add('valid-move-highlight');
                
                let displayRow = index.row;
                let displayCol = index.col;
                if (myColor === 'b') {
                    displayRow = 7 - index.row;
                    displayCol = 7 - index.col;
                }

                highlight.style.top = `${displayRow * 60}px`;
                highlight.style.left = `${displayCol * 60}px`;
                boardContainer.appendChild(highlight);
            }
        });
    }

    /**
     * 모든 하이라이트를 보드에서 제거
     */
    function clearHighlights() {
        document.querySelectorAll('.valid-move-highlight').forEach(el => el.remove());
    }
    
    /**
     * 체스 좌표('e4')를 인덱스({row: 4, col: 4})로 변환
     */
    function coordToIndex(coord) {
        const col = coord.charCodeAt(0) - 'a'.charCodeAt(0);
        const row = 8 - parseInt(coord[1]);
        return { row, col };
    }
    
    /**
     * 인덱스를 체스 좌표로 변환
     */
    function indexToCoord(row, col) {
        return `${String.fromCharCode('a'.charCodeAt(0) + col)}${8 - row}`;
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
            updateTurnAndState();
            
        } catch (error) {
            alert(`게임 정보를 불러오는 데 실패했습니다: ${error.message}`);
            window.location.href = './lobby.html';
        }
    }

    /**
     * 턴 상태를 업데이트하고, 턴에 따라 다음 행동을 결정 (수정됨)
     */
    function updateTurnAndState() {
        // 게임 종료 상태를 먼저 확인
        if (gameState.status === 'finished') {
            boardContainer.style.borderColor = '#999';
            // 게임 종료 메시지는 handleGameEnd에서 처리
            return;
        }

        const isMyTurn = (myColor === gameState.current_turn);

        if (isMyTurn) {
            gameStatusMessage.textContent = "당신의 턴입니다.";
            boardContainer.style.borderColor = 'gold';
            console.log("내 턴입니다.");
        } else {
            gameStatusMessage.textContent = "상대방의 턴입니다...";
            boardContainer.style.borderColor = '#333';
            // 롱 폴링은 여기서 다시 시작하지 않고, 이전 호출이 끝나면 자연스럽게 이어지도록 함
            // 대신, 초기화 시점과 내 턴이 끝나는 시점에만 호출
            waitForOpponentMove();
        }
    }

    /**
     * 롱 폴링으로 상대방의 이벤트를 기다림 (수정됨)
     */
    async function waitForOpponentMove() {

        if (myColor === gameState.current_turn || gameState.status === 'finished') {
            return; // 내 턴이거나 게임이 끝났으면 대기할 필요 없음
        }
        console.log("Waiting for opponent's move...");
        try {
            const response = await request(`/api/game/${gameId}/wait-for-move`);
            console.log("Received response from server:", response);
            if (response.status === 'updated' && response.data) {
                console.log("Opponent's move received:", response.data);
                handleServerUpdate(response.data);
            } else if (response.status === 'timeout') {
                // 타임아웃 시 재귀적으로 다시 호출
                waitForOpponentMove();
            }
        } catch (error) {
            console.error('Long polling error:', error);
            gameStatusMessage.textContent = `연결 오류. 3초 후 재시도...`;
            setTimeout(waitForOpponentMove, 3000);
        }
    }

    /**
     * 서버로부터 받은 모든 업데이트를 처리하는 중앙 함수 (수정됨)
     */
    function handleServerUpdate(data) {
        console.log("Server update received:", data); // <<--- 디버깅을 위한 로그 추가

        // 1. FEN 업데이트가 있으면 보드를 새로 그림
        if (data.fen) {
            gameState.fen = data.fen;
            const fenTurn = data.fen.split(' ')[1];
            // gameState.current_turn 업데이트는 서버 응답을 신뢰
            if (fenTurn) {
                gameState.current_turn = fenTurn;
            }
            renderBoard(gameState.fen, myUserInfo.board_skin_path, myUserInfo.piece_skin_path);
            
            if (data.isCheck) {
                // alert()는 게임 흐름을 방해하므로, 메시지로 대체
                gameStatusMessage.textContent = "체크!";
            }
        }
        
        // 2. 기타 이벤트 처리 (무승부, 기권 등)
        if (data.type === 'draw_offer') { /* ... */ }
        if (data.status === 'finished') { /* ... */ }

        // 3. 모든 업데이트 처리 후, UI 상태를 최종적으로 갱신
        updateTurnAndState();
        
    }
    
    // ================== 이벤트 처리 함수 ==================

    /**
     * 보드 클릭 이벤트를 총괄 처리
     */
    async function onBoardClick(event) {
        const target = event.target;
        
        // 하이라이트된 이동 경로를 클릭했을 때
        if (target.classList.contains('valid-move-highlight')) {
            const toRow = target.style.top.replace('px', '') / 60;
            const toCol = target.style.left.replace('px', '') / 60;

            let logicalRow = toRow;
            let logicalCol = toCol;
            if (myColor === 'b') {
                logicalRow = 7 - toRow;
                logicalCol = 7 - toCol;
            }
            
            const fromCoord = indexToCoord(parseInt(selectedPiece.dataset.row), parseInt(selectedPiece.dataset.col));
            const toCoord = indexToCoord(logicalRow, logicalCol);

            await handleMove(fromCoord, toCoord);
            return;
        }

        // 말을 클릭했을 때
        if (target.classList.contains('chess-piece')) {
            const pieceChar = target.dataset.piece;
            const isMyPiece = (myColor === 'w' && pieceChar === pieceChar.toUpperCase()) ||
                              (myColor === 'b' && pieceChar === pieceChar.toLowerCase());
            
            if (isMyPiece) {
                // 이미 선택된 말과 같은 말을 클릭하면 선택 취소
                if (selectedPiece === target) {
                    selectedPiece = null;
                    clearHighlights();
                } else {
                    selectedPiece = target;
                    const fromRow = parseInt(target.dataset.row);
                    const fromCol = parseInt(target.dataset.col);
                    const coord = indexToCoord(fromRow, fromCol);
                    
                    // 서버에 유효한 수 요청
                    try {
                        const moves = await request(`/api/game/${gameId}/move/${coord}`);
                        validMoves = moves;
                        showValidMoves(validMoves);
                    } catch (error) {
                        console.error('유효한 수를 가져오는 데 실패:', error);
                    }
                }
            }
        }
    }

    /**
     * 서버에 이동 요청을 보내고 UI를 업데이트
     */
    async function handleMove(from, to) {
        try {
            const moveData = { from, to };
            // TODO: 폰 승격 처리 로직 추가
            
            const response = await request(`/api/game/${gameId}/move`, 'POST', moveData);
            
            selectedPiece = null;
            clearHighlights();
            
            // 서버 응답을 중앙 처리 함수로 넘김
            handleServerUpdate({ fen: response.fen, isCheck: response.isCheck });

        } catch (error) {
            alert(`이동 실패: ${error.message}`);
            selectedPiece = null;
            clearHighlights();
        }
    }

    // ================== 초기 실행 ==================
    boardContainer.addEventListener('click', onBoardClick); // 이벤트 리스너 등록
    initializeGame();

});