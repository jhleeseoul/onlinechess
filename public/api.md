

## API 명세서 (API Specification)

**Base URL:** `http://localhost/onlinechess/public` (로컬 개발 환경). 프로덕션에서는 서버 URL로 변경

**인증 방식:** JWT (JSON Web Token)
- 로그인이 필요한 모든 API는 HTTP 요청 헤더에 다음 형식을 포함해야 합니다.
- `Authorization: Bearer <your_jwt_token>`

---

### **1. 인증 (Authentication)**

#### **1.1. 회원가입**
- **Endpoint:** `POST /api/users`
- **설명:** 새로운 사용자를 생성합니다.
- **Request Body:**
  ```json
  {
      "username": "testuser",
      "password": "password123",
      "nickname": "TestUser"
  }
  ```
- **Response (201 Created):**
  ```json
  {
      "message": "User created successfully.",
      "userId": 1
  }
  ```
- **Error Responses:**
  - `400 Bad Request`: 필수 필드가 누락된 경우.
  - `409 Conflict`: `username` 또는 `nickname`이 이미 존재하는 경우.

#### **1.2. 로그인**
- **Endpoint:** `POST /api/auth/login`
- **설명:** 사용자 인증 후 JWT 토큰을 발급합니다.
- **Request Body:**
  ```json
  {
      "username": "testuser",
      "password": "password123"
  }
  ```
- **Response (200 OK):**
  ```json
  {
      "message": "Login successful.",
      "token": "eyJ..."
  }
  ```
- **Error Responses:**
  - `401 Unauthorized`: 자격 증명(아이디 또는 비밀번호)이 유효하지 않은 경우.

---

### **2. 사용자 (Users)**

#### **2.1. 내 정보 조회**
- **Endpoint:** `GET /api/users/me`
- **인증:** **필수**
- **설명:** 현재 로그인한 사용자의 상세 정보를 조회합니다. (장착한 아이템 경로 포함)
- **Response (200 OK):**
  ```json
  {
      "id": 1,
      "username": "testuser",
      "nickname": "TestUser",
      "points": 1000,
      "coins": 500,
      "profile_icon_id": 1,
      "board_skin_id": 2,
      "piece_skin_id": 3,
      "profile_icon_path": "/assets/defaults/default_icon.png",
      "board_skin_path": "/assets/defaults/default_board.png",
      "piece_skin_path": "/assets/defaults/default_pieces/"
  }
  ```
- **Error Responses:**
  - `401 Unauthorized`: 인증 토큰이 없거나 유효하지 않은 경우.

#### **2.2. 내 정보 수정**
- **Endpoint:** `PATCH /api/users/me`
- **인증:** **필수**
- **설명:** 닉네임 또는 비밀번호를 수정합니다. 변경할 필드만 포함하며, 빈 값은 무시됩니다.
- **Request Body (예시):**
  ```json
  {
      "nickname": "NewNickname",
      "password": "newpassword123"
  }
  ```
- **Response (200 OK):**
  ```json
  {
      "message": "User information updated successfully."
  }
  ```
- **Error Responses:**
  - `400 Bad Request`: 비밀번호가 8자 미만일 때.
  - `409 Conflict`: 닉네임이 이미 사용 중일 때.

#### **2.3. 내 전적 조회**
- **Endpoint:** `GET /api/users/me/matches`
- **인증:** **필수**
- **설명:** 내가 플레이한 모든 게임의 기록을 조회합니다.
- **Response (200 OK):** `Array` of match objects.
  ```json
  [
      {
          "id": 1,
          "game_type": "rank",
          "result": "white_win",
          "end_reason": "checkmate",
          "start_at": "...",
          "end_at": "...",
          "my_color": "white",
          "opponent_nickname": "Opponent"
      }
  ]
  ```

#### **2.4. 내 인벤토리 조회**
- **Endpoint:** `GET /api/users/me/items`
- **인증:** **필수**
- **설명:** 내가 보유한 모든 아이템의 목록을 조회합니다. (장착 여부 포함)
- **Response (200 OK):** `Array` of item objects.
  ```json
  [
      {
          "user_item_id": 1,
          "item_id": 1,
          "item_type": "profile_icon",
          "name": "기본 프로필 아이콘",
          "asset_path": "/assets/defaults/default_icon.png",
          "acquired_at": "...",
          "is_equipped": 1
      }
  ]
  ```

#### **2.5. 아이템 장착**
- **Endpoint:** `POST /api/users/me/items/{userItemId}/equip`
- **인증:** **필수**
- **URL Parameters:**
  - `userItemId` (int): 장착할 아이템의 `user_items` 테이블 ID.
- **Response (200 OK):**
  ```json
  {
      "message": "Item equipped successfully."
  }
  ```

#### **2.6. 현재 진행 중인 게임 조회**
- **Endpoint:** `GET /api/users/me/current-game`
- **인증:** **필수**
- **설명:** 사용자가 현재 참여 중인 게임이 있는지 확인합니다. (재접속 기능용)
- **Response (200 OK):**
  ```json
  {
      "game_id": 123 // 진행 중인 게임이 없으면 null
  }
  ```

---

### **3. 매치메이킹 (Matchmaking)**

#### **3.1. 랭크 매치 요청**
- **Endpoint:** `POST /api/match/rank`
- **인증:** **필수**
- **설명:** 랭크 매치를 시작하거나 대기열에 등록합니다.
- **Response:**
  - **매칭 성공 시 (200 OK):**
    ```json
    {
        "status": "matched",
        "game_id": 123,
        "my_color": "white",
        "opponent": { ... }
    }
    ```
  - **대기 시작 시 (202 Accepted):**
    ```json
    {
        "status": "pending",
        "message": "Finding an opponent..."
    }
    ```

#### **3.2. 랭크 매치 대기 (롱 폴링)**
- **Endpoint:** `GET /api/match/wait`
- **인증:** **필수**
- **설명:** 매칭이 성사될 때까지 대기합니다.
- **Response:**
  - **매칭 성공 시 (200 OK):** `3.1`의 성공 응답과 동일.
  - **타임아웃 시 (200 OK):**
    ```json
    {
        "status": "pending",
        "message": "Still searching..."
    }
    ```

#### **3.3. 프라이빗 매치 방 생성**
- **Endpoint:** `POST /api/match/private`
- **인증:** **필수**
- **Response (201 Created):**
  ```json
  {
      "room_code": "A1B2C3"
  }
  ```

#### **3.4. 프라이빗 매치 대기 (롱 폴링)**
- **Endpoint:** `GET /api/match/private/wait/{roomCode}`
- **인증:** **필수**
- **URL Parameters:**
  - `roomCode` (string): `3.3`에서 받은 초대 코드.
- **Response:** `3.2`와 동일.

#### **3.5. 프라이빗 매치 참가**
- **Endpoint:** `POST /api/match/private/join`
- **인증:** **필수**
- **Request Body:**
  ```json
  {
      "room_code": "A1B2C3"
  }
  ```
- **Response (200 OK):** `3.1`의 성공 응답과 동일.

---

### **4. 게임 플레이 (Gameplay)**

*(모든 게임 플레이 API는 인증이 필수입니다)*

#### **4.1. 현재 게임 상태 조회**
- **Endpoint:** `GET /api/game/{gameId}/status`
- **설명:** 게임 페이지 진입 시, 현재 게임의 모든 상태 정보를 가져옵니다.
- **Response (200 OK):**
  ```json
  {
      "game_data": {
          "fen": "...", "current_turn": "w", ...
      },
      "white_player": { ... },
      "black_player": { ... }
  }
  ```
- **Error Responses:**
  - `401 Unauthorized`: 인증 토큰이 없거나 유효하지 않은 경우.
  - `403 Forbidden`: 게임 참가자가 아닌 경우.
  - `404 Not Found`: 게임이 존재하지 않는 경우.
  - `410 Gone`: 이미 종료된 게임일 경우.

#### **4.2. 유효한 이동 경로 조회**
- **Endpoint:** `GET /api/game/{gameId}/move/{coord}`
- **URL Parameters:**
  - `gameId` (int): 게임 ID.
  - `coord` (string): 체스 좌표 (e.g., `e2`).
- **Response (200 OK):** `Array` of coordinate strings.
  ```json
  ["e3", "e4"]
  ```

#### **4.3. 말 이동**
- **Endpoint:** `POST /api/game/{gameId}/move`
- **Request Body:**
  ```json
  {
      "from": "e2",
      "to": "e4",
      "promotion": "q" // 폰 승격 시에만 포함 (선택 사항)
  }
  ```
- **Response (200 OK):**
  ```json
  {
      "message": "Move successful",
      "fen": "...",
      "isCheck": false
  }
  ```
- **Error Responses:**
  - `400 Bad Request`: 필수 필드가 누락되거나, 유효하지 않은 이동일 때.
  - `401 Unauthorized`: 인증 토큰이 없거나 유효하지 않은 경우.
  - `403 Forbidden`: 자신의 턴이 아닐 때.

#### **4.4. 상대방 수 대기 (롱 폴링)**
- **Endpoint:** `GET /api/game/{gameId}/wait-for-move`
- **설명:** 상대방의 수를 기다립니다. 업데이트가 발생하면 즉시 응답하며, 30초 타임아웃 후 재요청 필요.
- **Response:**
  - **업데이트 발생 시 (200 OK):**
    ```json
    {
        "status": "updated",
        "data": {
            "fen": "...",
            "isCheck": false,
            "type": "move"  // 또는 "draw_offer", "draw_declined", "resign" 등
        }
    }
    ```
  - **타임아웃 시 (200 OK):** `{"status": "timeout"}`

#### **4.5. 기권**
- **Endpoint:** `POST /api/game/{gameId}/resign`
- **Response (200 OK):** `{"message": "You have resigned from the game."}`

#### **4.6. 게임 결과 상세 조회**
- **Endpoint:** `GET /api/game/{gameId}/result`
- **설명:** 게임 종료 후 결과 화면에 필요한 데이터를 조회합니다.
- **Response (200 OK):**
  ```json
  {
      "id": 1,
      "result": "white_win",
      "end_reason": "checkmate",
      ...
  }
  ```

#### **4.7. 무승부 제안 처리**
- **Endpoint:** `POST /api/game/{gameId}/draw`
- **인증:** **필수**
- **설명:** 무승부를 제안하거나, 상대방의 제안을 수락/거절합니다. 게임이 진행 중일 때만 가능하며, 자신의 턴에만 제안할 수 있습니다.
- **Request Body:**
  ```json
  {
      "action": "offer"  // "offer" (제안), "accept" (수락), "decline" (거절) 중 하나
  }
  ```
- **Response (200 OK):**
  - 제안 시: `{"message": "Draw offer sent."}`
  - 수락 시: `{"status": "finished", "result": {"result": "draw", "reason": "agreement"}}`
  - 거절 시: `{"message": "Draw offer declined."}`
- **Error Responses:**
  - `400 Bad Request`: 잘못된 action, 자신의 턴이 아닐 때 제안 시도, 이미 제안이 대기 중일 때.
  - `409 Conflict`: 이미 제안이 대기 중일 때.
  - `410 Gone`: 게임이 이미 종료된 경우.

---

### **5. 상점 (Shop)**

#### **5.1. 상점 아이템 목록 조회**
- **Endpoint:** `GET /api/shop/items`
- **인증:** 선택 (로그인 시 `is_owned` 정보 포함)
- **Response (200 OK):** `Array` of item objects.
  ```json
  [
      {
          "id": 4, "name": "골드 나이트 아이콘", "price": 500, ..., "is_owned": 0
      }
  ]
  ```

#### **5.2. 아이템 구매**
- **Endpoint:** `POST /api/shop/items/{itemId}/buy`
- **인증:** **필수**
- **URL Parameters:**
  - `itemId` (int): 구매할 아이템의 `items` 테이블 ID.
- **Response (200 OK):** `{"message": "구매가 완료되었습니다."}`
- **Error Responses:**
  - `400 Bad Request`: 코인이 부족하거나, 이미 보유한 아이템일 때.
  - `404 Not Found`: 아이템이 존재하지 않는 경우.

---

### **6. 기타 (Miscellaneous)**

#### **6.1. 리더보드 조회**
- **Endpoint:** `GET /api/leaderboard`
- **인증:** 불필요
- **Response (200 OK):** `Array` of user ranking objects.
  ```json
  [
      {
          "rank": "1",
          "id": 2,
          "nickname": "Player2",
          "points": 1016,
          "profile_icon_path": "..."
      }
  ]
  ```
