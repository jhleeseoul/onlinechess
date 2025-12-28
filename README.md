# ♟️ Pure PHP Online Chess Game Server

**PHP 실시간 온라인 체스 게임 서버 및 클라이언트**

이 프로젝트는 PHP에 대한 이해와 웹 기반 게임 서버 아키텍처 설계 능력을 증명하기 위해 기획되었습니다. 프레임워크의 도움 없이 순수 PHP로 MVC 패턴, RESTful API, 실시간 통신(Long Polling) 등 현대적인 웹 서버의 핵심 요소를 직접 구현하는 데 중점을 두었습니다.

---

## 📅 프로젝트 개요 (Overview)

*   **프로젝트 기간:** 2025.06.22~2025.12.19
*   **개발 인원:** 1인 (개인 프로젝트)
*   **목표:** PHP와 MySQL, Redis를 사용하여 확장 가능하고 안정적인 실시간 웹 게임 서버를 구축하고, Vanilla JS 클라이언트를 통해 모든 기능을 시각적으로 시연하는 풀스택 포트폴리오 제작.

## ✨ 주요 기능 (Features)

### 게임 플레이
- **실시간 체스 대전:** 랭크 매치 및 친구 초대를 위한 프라이빗 매치 지원.
- **완벽한 체스 규칙 구현:** 앙파상, 캐슬링, 폰 승격, 체크, 체크메이트, 스테일메이트 등 모든 특수 규칙을 서버 사이드에서 완벽하게 검증.
- **실시간 동기화:** 롱 폴링(Long Polling) 기법을 사용하여 한 플레이어의 움직임을 상대방에게 실시간으로 반영.
- **게임 재접속:** 게임 도중 연결이 끊기거나 새로고침해도 진행 중인 게임으로 자동 복귀.

### 소셜 및 경쟁
- **ELO 기반 랭크 시스템:** 게임 승패에 따라 ELO 레이팅 점수가 변동.
- **실시간 리더보드:** 전체 유저의 랭크 점수 순위를 조회.
- **프라이빗 매치:** 고유한 초대 코드를 생성하여 친구와 점수 변동 없이 플레이.

### 경제 시스템
- **게임 재화:** 게임 승리 또는 무승부 시 재화('코인') 획득.
- **상점:** 재화를 사용하여 프로필 아이콘, 보드 스킨, 말 스킨 등 다양한 꾸미기 아이템 구매.
- **인벤토리 및 아이템 장착:** 구매한 아이템을 확인하고 실제 게임에 적용.

---

## 🛠️ 기술 스택 (Tech Stack)

### Backend
- **Language:** **PHP 8.2** (No Frameworks)
- **Architecture:** **MVC (Model-View-Controller) 패턴**, 프론트 컨트롤러 패턴
- **Database:**
    - **MySQL (MariaDB):** 사용자 정보, 게임 기록, 아이템 등 영속 데이터 저장.
    - **Redis:**
        - 실시간 게임 상태 관리 (**Hash**)
        - 랭크 매치메이킹 큐 관리 (**Sorted Set**)
        - 프라이빗 매치 방 정보 관리 (**Hash**)
        - 롱 폴링을 위한 이벤트 큐 (**List**, `BRPOP`)
- **API:** **RESTful API** (JSON)
- **Authentication:** **JWT (JSON Web Token)**
- **Dependency Management:** **Composer** (PSR-4 Autoloading)
- **Web Server:** Apache (XAMPP)

### Frontend
- **Language:** **JavaScript (ES6+)**, HTML5, CSS3
- **Asynchronous:** **Fetch API**, **Async/Await**
- **Real-time:** **Long Polling**

### Development Environment
- **IDE:** Visual Studio Code
- **API Testing:** Postman
- **Dev Server:** VSCode Live Server (Frontend), XAMPP (Backend)

---

## 🏛️ 아키텍처 및 설계 결정

### Backend
- **Pure PHP와 MVC 패턴:** 프레임워크에 의존하지 않고 웹 애플리케이션의 핵심 동작 원리(라우팅, DB 연결, 상태 관리)를 직접 구현하여 PHP 언어 자체를 활용하는데 집중했습니다.
- **Redis의 전략적 활용:**
    - 매번 DB에 접근하는 부하를 줄이기 위해, 진행 중인 게임의 상태는 휘발성 메모리 DB인 Redis에 저장하고, 게임이 종료될 때만 최종 결과를 MySQL에 기록합니다.
    - **Time-based Search Widening** 매치메이킹 알고리즘을 도입하여, '빠른 매칭'과 '공정한 매칭' 사이의 균형을 맞추었습니다. 이는 대규모 유저 환경을 고려한 설계 역량을 보여줍니다.
- **Stateless 인증 (JWT):** 세션에 의존하지 않는 JWT 기반 인증을 통해, 추후 로드 밸런서 도입 및 서버 스케일 아웃(Scale-out)이 용이한 구조를 설계했습니다.
- **안전한 데이터 처리:** 모든 SQL 쿼리에 Prepared Statements를 사용하여 SQL Injection을 원천적으로 방어하고, 아이템 구매와 같은 중요한 로직에는 DB 트랜잭션과 비관적 락(`FOR UPDATE`)을 적용하여 데이터 정합성을 보장했습니다.

### Frontend
- **Vanilla JS:** 프론트엔드 라이브러리/프레임워크 학습보다 백엔드 API와의 순수한 상호작용 구현에 집중하기 위해 Vanilla JS를 선택했습니다.
- **이벤트 위임 패턴:** 동적으로 생성되는 다수의 DOM 요소(상점 아이템, 인벤토리 등)에 대한 이벤트 처리를 효율적으로 관리하기 위해 이벤트 위임 패턴을 적극 활용했습니다.
- **롱 폴링:** 웹소켓(WebSocket)과 같은 별도의 서버 기술 없이, HTTP 요청/응답 모델 내에서 실시간 통신을 구현하기 위해 롱 폴링 기법을 채택했습니다. 이는 "웹서버 구조의 게임 서버"라는 요구사항에 부합하는 기술 선택입니다.

---

## 🚀 실행 방법 (Getting Started)

### 사전 요구사항
- XAMPP (PHP 8.0+, Apache, MariaDB)
- Composer
- Redis Server
- `phpredis` PHP 확장 모듈 설치

### 설치 및 실행
1.  **저장소 클론:**
    ```bash
    git clone https://github.com/jhleeseoul/onlinechess.git
    cd onlinechess
    ```

2.  **서버(Backend) 설정:**
    - `onlinechess` 폴더를 XAMPP의 `htdocs` 디렉토리로 이동합니다.
    - 프로젝트 루트에서 Composer 의존성을 설치합니다.
      ```bash
      composer install
      ```
    - `.env` 파일을 생성하고 아래 내용을 환경에 맞게 수정합니다.
      ```ini
      DB_HOST=127.0.0.1
      DB_PORT=3306
      DB_DATABASE=onlinechess
      DB_USERNAME=root
      DB_PASSWORD=
      
      REDIS_HOST=127.0.0.1
      REDIS_PORT=6379
      
      JWT_SECRET=your_super_secret_key_12345
      ```
    - `phpMyAdmin`에서 `onlinechess` 데이터베이스를 생성하고, 프로젝트에 포함된 `database.sql` 파일을 가져오기(import)하여 모든 테이블을 생성합니다.
    - XAMPP 제어판에서 Apache와 MySQL 서버를 시작합니다.
    - Redis 서버를 실행합니다.

3.  **클라이언트(Frontend) 실행:**
    - VSCode에서 `onlinechess` 폴더를 엽니다.
    - `public/client/index.html` 파일을 오른쪽 클릭하여 "Open with Live Server"를 선택합니다.
    - 브라우저가 열리면 회원가입 후 게임을 즐길 수 있습니다.

---

## 💡 추후 개선 방향 (Future Enhancements)

- **WebSocket으로 마이그레이션:** 롱 폴링보다 더 효율적이고 지연 시간이 적은 양방향 통신을 위해 WebSocket(Ratchet 등) 도입을 고려할 수 있습니다.
- **서비스 계층(Service Layer) 분리:** 컨트롤러가 비대해지는 것을 방지하고 비즈니스 로직을 더욱 명확하게 분리하기 위해 서비스 계층을 도입.
- **단위 테스트(Unit Test) 작성:** PHPUnit을 사용하여 `ChessLogic` 모델과 같은 핵심 비즈니스 로직의 안정성을 보장.
- **CI/CD 파이프라인 구축:** GitHub Actions를 이용해 코드 푸시 시 자동으로 테스트 및 배포가 이루어지는 환경을 구축.
- **고도화된 무승부 규칙 구현:** 50수 규칙, 3회 동형 반복 등 추가적인 무승부 규칙을 `ChessLogic`에 구현.
