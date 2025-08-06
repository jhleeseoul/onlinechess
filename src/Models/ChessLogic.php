<?php

namespace App\Models;

class ChessLogic
{
    /** @var array 8x8 체스 보드, FEN을 파싱한 결과가 저장됨 */
    private array $board;

    /** @var string 'w' 또는 'b' */
    private string $currentTurn;

    // ... (캐슬링, 앙파상 등 다른 FEN 정보 프로퍼티들) ...
    /** @var string 캐슬링 가능 여부 (예: 'KQkq', 'Kq', '-') */
    private string $castlingAvailability;

    /** @var string|null 앙파상 목표 좌표 (예: 'e3') 또는 null */
    private ?string $enPassantTarget;

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

        [$fromRow, $fromCol] = $fromIndex;
        $piece = $this->board[$fromRow][$fromCol];

        if ($piece === null) return [];

        $isWhiteToMove = ctype_upper($piece);

        // 1. 우선, 규칙상 가능한 모든 이동 후보를 계산
        $candidateMoves = match (strtolower($piece)) {
            'p' => $this->getPawnMoves($fromRow, $fromCol, $piece),
            'r' => $this->getRookMoves($fromRow, $fromCol, $piece),
            'b' => $this->getBishopMoves($fromRow, $fromCol, $piece),
            'q' => $this->getQueenMoves($fromRow, $fromCol, $piece),
            'n' => $this->getKnightMoves($fromRow, $fromCol, $piece),
            'k' => $this->getKingMoves($fromRow, $fromCol, $piece),
            default => [],
        };
        
        $legalMoves = [];
        // 2. 각 후보 이동에 대해, 이동 후 우리 킹이 체크 상태가 되는지 시뮬레이션
        foreach ($candidateMoves as $moveCoord) {
            $toIndex = $this->coordToIndex($moveCoord);
            [$toRow, $toCol] = $toIndex;

            // 2-1. 임시로 말을 이동시켜 봄 (가상 보드)
            $originalPieceAtTarget = $this->board[$toRow][$toCol];
            $this->board[$toRow][$toCol] = $piece;
            $this->board[$fromRow][$fromCol] = null;
            
            // 2-2. 우리 킹의 위치를 찾음
            $kingPos = $this->findKing($isWhiteToMove);
            
            // 2-3. 그 위치가 상대에게 공격받지 않는다면 합법적인 수
            if ($kingPos && !$this->isSquareAttacked($kingPos[0], $kingPos[1], !$isWhiteToMove)) {
                $legalMoves[] = $moveCoord;
            }

            // 2-4. 보드를 원래 상태로 되돌림
            $this->board[$fromRow][$fromCol] = $piece;
            $this->board[$toRow][$toCol] = $originalPieceAtTarget;
        }

        return $legalMoves;
    }

    /**
     * 지정된 색의 킹 위치를 찾습니다.
     * @param bool $isWhiteKing 백(true)인지 흑(false)인지
     * @return array|null [row, col]
     */
    private function findKing(bool $isWhiteKing): ?array
    {
        $kingToFind = $isWhiteKing ? 'K' : 'k';
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ($this->board[$r][$c] === $kingToFind) {
                    return [$r, $c];
                }
            }
        }
        return null;
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
        
        // 4. 앙파상 공격
        if ($this->enPassantTarget !== null) {
            $epIndex = $this->coordToIndex($this->enPassantTarget);
            $epRow = $epIndex[0];
            $epCol = $epIndex[1];
            
            // 앙파상 공격이 가능한지 확인
            // 1. 목표 행이 올바른가 (백은 2행, 흑은 5행에서만 가능)
            // 2. 내 폰의 위치가 올바른가 (백은 3행, 흑은 4행)
            // 3. 목표 열이 내 폰의 바로 옆인가
            if ($oneStepRow === $epRow && abs($col - $epCol) === 1) {
                 $moves[] = $this->enPassantTarget;
            }
        }

        // 승급 규칙은 나중에

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

    private function getKnightMoves(int $row, int $col, string $piece): array
    {
        $moves = [];
        $isWhite = ctype_upper($piece);
        
        // 나이트가 이동할 수 있는 8가지 방향 (row 변화량, col 변화량)
        $knightMoves = [
            [-2, -1], [-2, 1], // 위로 두 칸, 좌/우로 한 칸
            [-1, -2], [-1, 2], // 위로 한 칸, 좌/우로 두 칸
            [1, -2], [1, 2],   // 아래로 한 칸, 좌/우로 두 칸
            [2, -1], [2, 1]    // 아래로 두 칸, 좌/우로 한 칸
        ];

        foreach ($knightMoves as $move) {
            [$dr, $dc] = $move;
            $targetRow = $row + $dr;
            $targetCol = $col + $dc;

            // 1. 목표 위치가 보드 안에 있는지 확인
            if ($targetRow >= 0 && $targetRow <= 7 && $targetCol >= 0 && $targetCol <= 7) {
                $targetPiece = $this->board[$targetRow][$targetCol];
                // 2. 목표 위치가 비어있거나, 상대방의 말인지 확인
                if ($targetPiece === null) {
                    $moves[] = $this->indexToCoord($targetRow, $targetCol);
                } else {
                    $isTargetWhite = ctype_upper($targetPiece);
                    if ($isWhite !== $isTargetWhite) {
                        $moves[] = $this->indexToCoord($targetRow, $targetCol);
                    }
                }
            }
        }
        return $moves;
    }

    private function getKingMoves(int $row, int $col, string $piece): array
    {
        $moves = [];
        $isWhite = ctype_upper($piece);

        // 킹이 이동할 수 있는 8방향
        $kingDirections = [
            [-1, -1], [-1, 0], [-1, 1],
            [0, -1],           [0, 1],
            [1, -1],  [1, 0],  [1, 1]
        ];

        foreach ($kingDirections as $direction) {
            [$dr, $dc] = $direction;
            $targetRow = $row + $dr;
            $targetCol = $col + $dc;
            
            if ($targetRow >= 0 && $targetRow <= 7 && $targetCol >= 0 && $targetCol <= 7) {
                $targetPiece = $this->board[$targetRow][$targetCol];
                if ($targetPiece === null) {
                    $moves[] = $this->indexToCoord($targetRow, $targetCol);
                } else {
                    $isTargetWhite = ctype_upper($targetPiece);
                    if ($isWhite !== $isTargetWhite) {
                        $moves[] = $this->indexToCoord($targetRow, $targetCol);
                    }
                }
            }
        }
        
        // 캐슬링 가능 여부 확인
        if (!$this->isSquareAttacked($row, $col, !$isWhite)) { // 현재 킹이 체크 상태가 아닐 때만
            // 킹사이드 캐슬링 (O-O)
            $canCastleKingside = $isWhite ? str_contains($this->castlingAvailability, 'K') : str_contains($this->castlingAvailability, 'k');
            if ($canCastleKingside && $this->board[$row][$col+1] === null && $this->board[$row][$col+2] === null) {
                if (!$this->isSquareAttacked($row, $col+1, !$isWhite) && !$this->isSquareAttacked($row, $col+2, !$isWhite)) {
                    $moves[] = $this->indexToCoord($row, $col+2);
                }
            }

            // 퀸사이드 캐슬링 (O-O-O)
            $canCastleQueenside = $isWhite ? str_contains($this->castlingAvailability, 'Q') : str_contains($this->castlingAvailability, 'q');
            if ($canCastleQueenside && $this->board[$row][$col-1] === null && $this->board[$row][$col-2] === null && $this->board[$row][$col-3] === null) {
                if (!$this->isSquareAttacked($row, $col-1, !$isWhite) && !$this->isSquareAttacked($row, $col-2, !$isWhite)) {
                    $moves[] = $this->indexToCoord($row, $col-2);
                }
            }
        }
        
        return $moves;
    }
        
    /**
     * 특정 좌표가 지정된 색의 플레이어에게 공격받고 있는지 확인합니다.
     * getValidMoves와 반대 관점에서 접근합니다.
     * 즉, 해당 좌표가 공격받고 있다면 true, 그렇지 않다면 false를 반환합니다.
     * @param int $row 확인할 행
     * @param int $col 확인할 열
     * @param bool $isWhiteAttacker 공격하는 쪽이 백(true)인지 흑(false)인지
     * @return bool
     */
    public function isSquareAttacked(int $row, int $col, bool $isWhiteAttacker): bool
    {
        // 1. 상대방 폰의 공격을 확인
        $pawnDirection = $isWhiteAttacker ? 1 : -1;
        $pawnAttackRow = $row + $pawnDirection;
        foreach ([-1, 1] as $pawnAttackColOffset) {
            $pawnAttackCol = $col + $pawnAttackColOffset;
            if ($pawnAttackRow >= 0 && $pawnAttackRow <= 7 && $pawnAttackCol >= 0 && $pawnAttackCol <= 7) {
                $piece = $this->board[$pawnAttackRow][$pawnAttackCol];
                if ($piece !== null && strtolower($piece) === 'p' && ctype_upper($piece) === $isWhiteAttacker) {
                    return true;
                }
            }
        }

        // 2. 상대방 나이트의 공격을 확인
        $knightMoves = [[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]];
        foreach ($knightMoves as $move) {
            $nRow = $row + $move[0];
            $nCol = $col + $move[1];
            if ($nRow >= 0 && $nRow <= 7 && $nCol >= 0 && $nCol <= 7) {
                $piece = $this->board[$nRow][$nCol];
                if ($piece !== null && strtolower($piece) === 'n' && ctype_upper($piece) === $isWhiteAttacker) {
                    return true;
                }
            }
        }
        
        // 3. 직선 공격 확인 (상대방 룩, 퀸)
        $rookDirections = [[-1,0],[1,0],[0,-1],[0,1]];
        foreach ($rookDirections as $direction) {
            $tempRow = $row; $tempCol = $col;
            while (true) {
                $tempRow += $direction[0];
                $tempCol += $direction[1];
                if ($tempRow < 0 || $tempRow > 7 || $tempCol < 0 || $tempCol > 7) break;
                $piece = $this->board[$tempRow][$tempCol];
                if ($piece !== null) {
                    if (ctype_upper($piece) === $isWhiteAttacker && (strtolower($piece) === 'r' || strtolower($piece) === 'q')) {
                        return true;
                    }
                    break; // 다른 말이 길을 막고 있음
                }
            }
        }

        // 4. 대각선 공격 확인 (상대방 비숍, 퀸)
        $bishopDirections = [[-1,-1],[-1,1],[1,-1],[1,1]];
        foreach ($bishopDirections as $direction) {
            $tempRow = $row; $tempCol = $col;
            while (true) {
                $tempRow += $direction[0];
                $tempCol += $direction[1];
                if ($tempRow < 0 || $tempRow > 7 || $tempCol < 0 || $tempCol > 7) break;
                $piece = $this->board[$tempRow][$tempCol];
                if ($piece !== null) {
                    if (ctype_upper($piece) === $isWhiteAttacker && (strtolower($piece) === 'b' || strtolower($piece) === 'q')) {
                        return true;
                    }
                    break;
                }
            }
        }

        // 5. 상대방 킹의 공격 확인
        $kingMoves = [[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]];
        foreach ($kingMoves as $move) {
            $kRow = $row + $move[0];
            $kCol = $col + $move[1];
            if ($kRow >= 0 && $kRow <= 7 && $kCol >= 0 && $kCol <= 7) {
                $piece = $this->board[$kRow][$kCol];
                if ($piece !== null && strtolower($piece) === 'k' && ctype_upper($piece) === $isWhiteAttacker) {
                    return true;
                }
            }
        }

        return false; // 아무에게도 공격받지 않음
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
        $this->castlingAvailability = $parts[2];
        $this->enPassantTarget = ($parts[3] === '-') ? null : $parts[3];

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

    /**
     * 현재 보드 상태를 FEN 문자열로 변환합니다.
     * parseFen과 반대 작업을 수행합니다.
     * @return string
     */
    public function toFen(): string
    {
        $fen = '';
        // 1. 말 배치
        for ($r = 0; $r < 8; $r++) {
            $emptyCount = 0;
            for ($c = 0; $c < 8; $c++) {
                $piece = $this->board[$r][$c];
                if ($piece === null) {
                    $emptyCount++;
                } else {
                    if ($emptyCount > 0) {
                        $fen .= $emptyCount;
                        $emptyCount = 0;
                    }
                    $fen .= $piece;
                }
            }
            if ($emptyCount > 0) $fen .= $emptyCount;
            if ($r < 7) $fen .= '/';
        }

        // 2. 나머지 정보
        $fen .= ' ' . $this->currentTurn;
        $fen .= ' ' . $this->castlingAvailability;
        $fen .= ' ' . ($this->enPassantTarget ?? '-');
        $fen .= ' 0 1'; // 50수 규칙, 턴 카운터는 단순화

        return $fen;
    }
    
    /**
     * 말을 이동시키고, 새로운 상태의 ChessLogic 객체를 반환합니다.
     * @param string $fromCoord
     * @param string $toCoord
     * @return ChessLogic|null 이동이 불가능하면 null
     */
    public function move(string $fromCoord, string $toCoord): ?ChessLogic
    {
        $validMoves = $this->getValidMovesForPiece($fromCoord);
        if (!in_array($toCoord, $validMoves)) {
            return null; // 불가능한 이동
        }

        // 1. 새로운 객체에 현재 상태 복사
        $newLogic = clone $this;
        $from = $newLogic->coordToIndex($fromCoord);
        $to = $newLogic->coordToIndex($toCoord);
        $piece = $newLogic->board[$from[0]][$from[1]];

        // 2. 말 이동
        $newLogic->board[$to[0]][$to[1]] = $piece;
        $newLogic->board[$from[0]][$from[1]] = null;

        // 3. 특수 이동 처리 (앙파상, 캐슬링)
        // ... (생략, 다음 단계에서 구현)

        // 4. 상태 업데이트 (턴, 캐슬링 가능 여부, 앙파상 타겟)
        $newLogic->currentTurn = ($this->currentTurn === 'w') ? 'b' : 'w';
        
        // 폰이 2칸 전진하면 앙파상 타겟 설정
        if (strtolower($piece) === 'p' && abs($from[0] - $to[0]) === 2) {
            $newLogic->enPassantTarget = $this->indexToCoord(($from[0] + $to[0]) / 2, $from[1]);
        } else {
            $newLogic->enPassantTarget = null;
        }

        // 킹이나 룩이 움직이면 캐슬링 권한 상실
        // ... (생략, 다음 단계에서 구현)

        return $newLogic;
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