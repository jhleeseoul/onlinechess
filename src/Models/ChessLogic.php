<?php

namespace App\Models;

class ChessLogic
{
    /** @var array 8x8 체스 보드, FEN을 파싱한 결과가 저장됨 */
    private array $board;

    /** @var string 'w' 또는 'b' */
    private string $currentTurn;

    // ... (캐슬링, 앙파상 등 다른 FEN 정보 프로퍼티들) ...

    public function __construct(string $fen)
    {
        $this->parseFen($fen);
    }

    /**
     * 특정 좌표에 있는 말의 유효한 모든 이동 위치를 반환합니다.
     * @param string $coord 예: "e2"
     * @return array 유효한 이동 좌표 목록 예: ["e3", "e4"]
     */
    public function getValidMovesForPiece(string $coord): array
    {
        $fromIndex = $this->coordToIndex($coord);
        if ($fromIndex === null) return [];

        [$row, $col] = $fromIndex;
        $piece = $this->board[$row][$col];

        if ($piece === null) return [];
        
        // 말의 종류에 따라 적절한 메소드 호출
        return match (strtolower($piece)) {
            'p' => $this->getPawnMoves($row, $col, $piece),
            'r' => $this->getRookMoves($row, $col, $piece),     // <--- 추가
            'b' => $this->getBishopMoves($row, $col, $piece),  // <--- 추가
            'q' => $this->getQueenMoves($row, $col, $piece),   // <--- 추가
            // 'n' => $this->getKnightMoves($row, $col, $piece), // 나중에 추가
            // 'k' => $this->getKingMoves($row, $col, $piece),   // 나중에 추가
            default => [],
        };
    }

    /**
     * 폰의 유효한 이동을 계산합니다.
     * @param int $row
     * @param int $col
     * @param string $piece 'P' 또는 'p'
     * @return array
     */
    private function getPawnMoves(int $row, int $col, string $piece): array
    {
        $moves = [];
        $isWhite = ctype_upper($piece);
        $direction = $isWhite ? -1 : 1; // 백은 위로(-1), 흑은 아래로(+1)

        // 1. 한 칸 전진
        $oneStepRow = $row + $direction;
        // 보드 범위 안에 있는지 먼저 확인
        if ($oneStepRow >= 0 && $oneStepRow <= 7) {
            // 해당 칸이 비어있는지 확인 (isset 대신 직접 접근 후 null 비교)
            if ($this->board[$oneStepRow][$col] === null) {
                $moves[] = $this->indexToCoord($oneStepRow, $col);

                // 2. 첫 수일 때 두 칸 전진 (한 칸 전진이 가능할 때만 체크)
                $startingRow = $isWhite ? 6 : 1;
                $twoStepsRow = $row + (2 * $direction);
                if ($row === $startingRow && $this->board[$twoStepsRow][$col] === null) {
                    $moves[] = $this->indexToCoord($twoStepsRow, $col);
                }
            }
        }
        
        // 3. 대각선 공격
        $attackCols = [$col - 1, $col + 1];
        if ($oneStepRow >= 0 && $oneStepRow <= 7) { // 공격할 행이 보드 안에 있는지 확인
            foreach ($attackCols as $attackCol) {
                if ($attackCol >= 0 && $attackCol <= 7) { // 공격할 열이 보드 안에 있는지 확인
                    $targetPiece = $this->board[$oneStepRow][$attackCol];
                    if ($targetPiece !== null) { // 목표 칸에 말이 있어야 함
                        $isTargetWhite = ctype_upper($targetPiece);
                        if ($isWhite !== $isTargetWhite) { // 상대방 말일 경우
                            $moves[] = $this->indexToCoord($oneStepRow, $attackCol);
                        }
                    }
                }
            }
        }
        
        // 앙파상, 승급 규칙은 나중에 추가...
        return $moves;
    }

    /**
     * 직선/대각선으로 미끄러지듯 움직이는 말(룩, 비숍, 퀸)의 유효한 이동을 계산합니다.
     * @param int $row 시작 행
     * @param int $col 시작 열
     * @param string $piece 현재 말 ('R', 'b', 'Q' 등)
     * @param array $directions 이동할 방향들의 배열 예: [[-1, 0], [1, 0]] (상, 하)
     * @return array
     */
    private function getSlidingMoves(int $row, int $col, string $piece, array $directions): array
    {
        $moves = [];
        $isWhite = ctype_upper($piece);

        foreach ($directions as $direction) {
            [$dr, $dc] = $direction;
            $currentRow = $row + $dr;
            $currentCol = $col + $dc;

            // 해당 방향으로 계속 탐색
            while ($currentRow >= 0 && $currentRow <= 7 && $currentCol >= 0 && $currentCol <= 7) {
                $targetPiece = $this->board[$currentRow][$currentCol];

                if ($targetPiece === null) {
                    // 1. 빈 칸이면 이동 가능 목록에 추가하고 계속 탐색
                    $moves[] = $this->indexToCoord($currentRow, $currentCol);
                } else {
                    $isTargetWhite = ctype_upper($targetPiece);
                    // 2. 상대방 말이면, 잡을 수 있으므로 이동 목록에 추가하고 탐색 중단
                    if ($isWhite !== $isTargetWhite) {
                        $moves[] = $this->indexToCoord($currentRow, $currentCol);
                    }
                    // 3. 우리 편 말이든 상대방 말이든, 경로가 막혔으므로 해당 방향 탐색 중단
                    break;
                }
                
                $currentRow += $dr;
                $currentCol += $dc;
            }
        }
        return $moves;
    }

    private function getRookMoves(int $row, int $col, string $piece): array
    {
        $directions = [
            [-1, 0], // 위
            [1, 0],  // 아래
            [0, -1], // 왼쪽
            [0, 1]   // 오른쪽
        ];
        return $this->getSlidingMoves($row, $col, $piece, $directions);
    }

    private function getBishopMoves(int $row, int $col, string $piece): array
    {
        $directions = [
            [-1, -1], // 왼쪽 위
            [-1, 1],  // 오른쪽 위
            [1, -1],  // 왼쪽 아래
            [1, 1]    // 오른쪽 아래
        ];
        return $this->getSlidingMoves($row, $col, $piece, $directions);
    }

    private function getQueenMoves(int $row, int $col, string $piece): array
    {
        // 퀸은 룩의 움직임과 비숍의 움직임을 합친 것과 같습니다.
        $rookMoves = $this->getRookMoves($row, $col, $piece);
        $bishopMoves = $this->getBishopMoves($row, $col, $piece);
        return array_merge($rookMoves, $bishopMoves);
    }

    /**
     * FEN 문자열을 파싱하여 클래스 프로퍼티를 초기화합니다.
     * 즉, 이차원 배열 형태의 board 상태를 생성합니다.
     * @param string $fen
     */
    private function parseFen(string $fen): void
    {
        $parts = explode(' ', $fen);
        $piecePlacement = $parts[0];
        $this->currentTurn = $parts[1];
        // ... (나머지 부분 파싱 로직은 나중에 추가)

        // 보드 상태 파싱
        $this->board = [];
        $rows = explode('/', $piecePlacement);
        for ($r = 0; $r < 8; $r++) {
            $this->board[$r] = [];
            $col = 0;
            foreach (str_split($rows[$r]) as $char) {
                if (is_numeric($char)) {
                    for ($i = 0; $i < (int)$char; $i++) {
                        $this->board[$r][$col] = null; // 빈 칸
                        $col++;
                    }
                } else {
                    $this->board[$r][$col] = $char; // 말
                    $col++;
                }
            }
        }
    }

    // 테스트용: 현재 보드 상태를 보기 쉽게 출력하는 메소드
    public function getBoard(): array
    {
        return $this->board;
    }

    /**
     * 체스 좌표(예: 'e4')를 배열 인덱스(예: [4, 4])로 변환합니다.
     * @param string $coord
     * @return array|null [row, col]
     */
    private function coordToIndex(string $coord): ?array
    {
        if (strlen($coord) !== 2) return null;
        $col = ord(strtolower($coord[0])) - ord('a');
        $row = 8 - (int)$coord[1];
        if ($row < 0 || $row > 7 || $col < 0 || $col > 7) {
            return null;
        }
        return [$row, $col];
    }
    
    /**
     * 배열 인덱스를 체스 좌표로 변환합니다.
     * @param int $row
     * @param int $col
     * @return string
     */
    private function indexToCoord(int $row, int $col): string
    {
        return chr(ord('a') + $col) . (8 - $row);
    }
}