"use client"

import type React from "react"
import { useEffect, useRef, useState, useCallback } from "react"
import type { Player } from "@/hooks/useWebSocket"
import {
  GAME_CONFIG,
  CHARACTER_STATS,
  KEY_CODES,
  clampPosition,
  isInAttackRange,
  getHealthPercentage,
  getHealthColor,
  isPlayerAlive,
  getPlayerDisplayName,
  isCooldownReady,
  getCooldownPercentage,
} from "@/lib/gameLogic"

interface ArenaProps {
  players: Record<string, Player>
  currentPlayerId: string | null
  onMove: (position: { x: number; y: number }) => void
  onAttack: (targetId: string) => void
  gameStatus: "waiting" | "active" | "finished"
}

interface KeyState {
  [key: string]: boolean
}

interface DamageIndicator {
  id: string
  targetId: string
  damage: number
  position: { x: number; y: number }
  createdAt: number
}

export const Arena: React.FC<ArenaProps> = ({ players, currentPlayerId, onMove, onAttack, gameStatus }) => {
  const arenaRef = useRef<HTMLDivElement>(null)
  const [keys, setKeys] = useState<KeyState>({})
  const [selectedTarget, setSelectedTarget] = useState<string | null>(null)
  const [lastAttackTime, setLastAttackTime] = useState<number>(0)
  const [lastMoveTime, setLastMoveTime] = useState<number>(0)
  const [damageIndicators, setDamageIndicators] = useState<DamageIndicator[]>([])
  const [attackAnimations, setAttackAnimations] = useState<{ [playerId: string]: boolean }>({})
  const moveIntervalRef = useRef<NodeJS.Timeout | null>(null)
  const currentPlayer = currentPlayerId ? players[currentPlayerId] : null

  // Clean up expired damage indicators
  useEffect(() => {
    const interval = setInterval(() => {
      setDamageIndicators((prev) =>
        prev.filter((indicator) => Date.now() - indicator.createdAt < GAME_CONFIG.damageDisplayDuration),
      )
    }, 500)

    return () => clearInterval(interval)
  }, [])

  // Handle keyboard input
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      const key = e.key.toLowerCase()

      // Movement keys
      if ([...KEY_CODES.UP, ...KEY_CODES.DOWN, ...KEY_CODES.LEFT, ...KEY_CODES.RIGHT].includes(key)) {
        e.preventDefault()
        setKeys((prev) => ({ ...prev, [key]: true }))
      }

      // Attack key
      if (KEY_CODES.ATTACK.includes(key)) {
        e.preventDefault()
        handleAttack()
      }
    }

    const handleKeyUp = (e: KeyboardEvent) => {
      const key = e.key.toLowerCase()
      setKeys((prev) => ({ ...prev, [key]: false }))
    }

    window.addEventListener("keydown", handleKeyDown)
    window.addEventListener("keyup", handleKeyUp)

    return () => {
      window.removeEventListener("keydown", handleKeyDown)
      window.removeEventListener("keyup", handleKeyUp)
    }
  }, [])

  // Handle movement
  const handleMovement = useCallback(() => {
    if (!currentPlayer || !isPlayerAlive(currentPlayer) || gameStatus !== "active") {
      return
    }

    // Check move cooldown
    if (!isCooldownReady(lastMoveTime, GAME_CONFIG.moveCooldown)) {
      return
    }

    let dx = 0
    let dy = 0
    const speed = CHARACTER_STATS[currentPlayer.characterClass].speed

    if (KEY_CODES.UP.some((key) => keys[key])) dy -= speed
    if (KEY_CODES.DOWN.some((key) => keys[key])) dy += speed
    if (KEY_CODES.LEFT.some((key) => keys[key])) dx -= speed
    if (KEY_CODES.RIGHT.some((key) => keys[key])) dx += speed

    if (dx !== 0 || dy !== 0) {
      const newPosition = clampPosition({
        x: currentPlayer.position.x + dx,
        y: currentPlayer.position.y + dy,
      })

      setLastMoveTime(Date.now())
      onMove(newPosition)
    }
  }, [keys, currentPlayer, onMove, gameStatus, lastMoveTime])

  // Movement interval
  useEffect(() => {
    const hasMovementKeys = [...KEY_CODES.UP, ...KEY_CODES.DOWN, ...KEY_CODES.LEFT, ...KEY_CODES.RIGHT].some(
      (key) => keys[key],
    )

    if (hasMovementKeys && !moveIntervalRef.current) {
      moveIntervalRef.current = setInterval(handleMovement, 16) // ~60 FPS movement
    } else if (!hasMovementKeys && moveIntervalRef.current) {
      clearInterval(moveIntervalRef.current)
      moveIntervalRef.current = null
    }

    return () => {
      if (moveIntervalRef.current) {
        clearInterval(moveIntervalRef.current)
        moveIntervalRef.current = null
      }
    }
  }, [keys, handleMovement])

  // Handle attack
  const handleAttack = useCallback(() => {
    if (!currentPlayer || !selectedTarget || !isPlayerAlive(currentPlayer) || gameStatus !== "active") {
      return
    }

    const target = players[selectedTarget]
    if (!target || !isPlayerAlive(target)) {
      setSelectedTarget(null)
      return
    }

    // Check cooldown
    if (!isCooldownReady(lastAttackTime, GAME_CONFIG.attackCooldown)) {
      return
    }

    // Check range
    if (!isInAttackRange(currentPlayer, target)) {
      // Show "out of range" indicator
      setDamageIndicators((prev) => [
        ...prev,
        {
          id: `range-${Date.now()}`,
          targetId: selectedTarget,
          damage: -1, // Special value for "out of range"
          position: {
            x: target.position.x + GAME_CONFIG.playerSize / 2,
            y: target.position.y - 20,
          },
          createdAt: Date.now(),
        },
      ])
      return
    }

    // Show attack animation
    setAttackAnimations((prev) => ({ ...prev, [currentPlayer.id]: true }))
    setTimeout(() => {
      setAttackAnimations((prev) => ({ ...prev, [currentPlayer.id]: false }))
    }, 300)

    setLastAttackTime(Date.now())
    onAttack(selectedTarget)

    // Add damage indicator (this will be updated with actual damage from server)
    const damage = CHARACTER_STATS[currentPlayer.characterClass].damage
    setDamageIndicators((prev) => [
      ...prev,
      {
        id: `damage-${Date.now()}`,
        targetId: selectedTarget,
        damage,
        position: {
          x: target.position.x + GAME_CONFIG.playerSize / 2,
          y: target.position.y - 20,
        },
        createdAt: Date.now(),
      },
    ])
  }, [currentPlayer, selectedTarget, players, onAttack, lastAttackTime, gameStatus])

  const handlePlayerClick = useCallback(
    (playerId: string) => {
      if (!currentPlayer || playerId === currentPlayerId || !isPlayerAlive(players[playerId])) {
        return
      }

      setSelectedTarget(playerId)
    },
    [currentPlayer, currentPlayerId, players],
  )

  const getPlayerStyle = (player: Player) => {
    const stats = CHARACTER_STATS[player.characterClass]
    return {
      left: `${player.position.x}px`,
      top: `${player.position.y}px`,
      backgroundColor: stats.color,
      opacity: isPlayerAlive(player) ? 1 : 0.3,
      transform: selectedTarget === player.id ? "scale(1.1)" : "scale(1)",
      border:
        selectedTarget === player.id
          ? "3px solid #ffeb3b"
          : player.id === currentPlayerId
            ? "3px solid #ff9800"
            : "2px solid rgba(255,255,255,0.3)",
    }
  }

  const renderAttackRange = () => {
    if (!currentPlayer || !isPlayerAlive(currentPlayer) || gameStatus !== "active") {
      return null
    }

    const range = CHARACTER_STATS[currentPlayer.characterClass].range
    const centerX = currentPlayer.position.x + GAME_CONFIG.playerSize / 2
    const centerY = currentPlayer.position.y + GAME_CONFIG.playerSize / 2

    return (
      <div
        className="absolute border-2 border-dashed border-yellow-400 rounded-full pointer-events-none opacity-30"
        style={{
          left: `${centerX - range}px`,
          top: `${centerY - range}px`,
          width: `${range * 2}px`,
          height: `${range * 2}px`,
          transform: "translate(-50%, -50%)",
        }}
      />
    )
  }

  const renderCooldownOverlay = () => {
    if (!currentPlayer || !isPlayerAlive(currentPlayer) || gameStatus !== "active") {
      return null
    }

    const cooldownPercentage = getCooldownPercentage(lastAttackTime, GAME_CONFIG.attackCooldown)
    if (cooldownPercentage <= 0) return null

    return (
      <div
        className="absolute inset-0 bg-black pointer-events-none rounded-full flex items-center justify-center"
        style={{
          opacity: cooldownPercentage / 200 + 0.1, // Max opacity 0.6
        }}
      >
        <div className="text-white font-bold text-xs">
          {Math.ceil((cooldownPercentage / 100) * (GAME_CONFIG.attackCooldown / 1000))}s
        </div>
      </div>
    )
  }

  return (
    <div className="relative">
      {/* Arena */}
      <div
        ref={arenaRef}
        className="relative bg-gradient-to-br from-gray-800 to-gray-900 border-4 border-orange-500 rounded-lg overflow-hidden"
        style={{
          width: `${GAME_CONFIG.arenaWidth}px`,
          height: `${GAME_CONFIG.arenaHeight}px`,
          backgroundImage: `
            linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%),
            linear-gradient(-45deg, rgba(255,255,255,0.1) 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.1) 75%),
            linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.1) 75%)
          `,
          backgroundSize: "40px 40px",
          backgroundPosition: "0 0, 0 20px, 20px -20px, -20px 0px",
        }}
      >
        {/* Attack Range Indicator */}
        {renderAttackRange()}

        {/* Players */}
        {Object.values(players).map((player) => {
          const healthPercentage = getHealthPercentage(player.health, player.maxHealth)
          const healthColor = getHealthColor(healthPercentage)
          const isAttacking = attackAnimations[player.id]

          return (
            <div
              key={player.id}
              className={`absolute flex items-center justify-center text-white font-bold text-sm cursor-pointer transition-all duration-200 rounded-full shadow-lg ${
                isAttacking ? "animate-pulse" : ""
              } ${selectedTarget === player.id ? "ring-2 ring-yellow-400 ring-offset-2" : ""}`}
              style={{
                ...getPlayerStyle(player),
                width: `${GAME_CONFIG.playerSize}px`,
                height: `${GAME_CONFIG.playerSize}px`,
                zIndex: player.id === currentPlayerId ? 10 : 5,
              }}
              onClick={() => handlePlayerClick(player.id)}
              title={`${getPlayerDisplayName(player)} (${player.characterClass})`}
            >
              {/* Character Initial */}
              <span className="text-shadow">{player.characterName.charAt(0).toUpperCase()}</span>

              {/* Health Bar */}
              <div className="absolute -top-2 left-1/2 transform -translate-x-1/2 w-12 h-1.5 bg-black bg-opacity-50 rounded-full overflow-hidden">
                <div
                  className="h-full transition-all duration-300 rounded-full"
                  style={{
                    width: `${healthPercentage}%`,
                    backgroundColor: healthColor,
                  }}
                />
              </div>

              {/* Player Name */}
              <div className="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-xs text-white text-center whitespace-nowrap bg-black bg-opacity-50 px-1 rounded">
                {getPlayerDisplayName(player)}
              </div>

              {/* Class Icon */}
              <div className="absolute -top-6 left-1/2 transform -translate-x-1/2 text-xs">
                {player.characterClass === "warrior" && "⚔️"}
                {player.characterClass === "archer" && "🏹"}
                {player.characterClass === "mage" && "🔮"}
              </div>

              {/* Cooldown Overlay (only for current player) */}
              {player.id === currentPlayerId && renderCooldownOverlay()}

              {/* Death Overlay */}
              {!isPlayerAlive(player) && (
                <div className="absolute inset-0 bg-black bg-opacity-60 rounded-full flex items-center justify-center">
                  <span className="text-red-500 text-lg">💀</span>
                </div>
              )}
            </div>
          )
        })}

        {/* Damage Indicators */}
        {damageIndicators.map((indicator) => (
          <div
            key={indicator.id}
            className={`absolute pointer-events-none animate-bounce-up text-sm font-bold ${
              indicator.damage === -1 ? "text-gray-400" : "text-red-500"
            }`}
            style={{
              left: `${indicator.position.x}px`,
              top: `${indicator.position.y}px`,
              transform: "translate(-50%, -100%)",
              opacity: 1 - (Date.now() - indicator.createdAt) / GAME_CONFIG.damageDisplayDuration,
            }}
          >
            {indicator.damage === -1 ? "Out of range!" : `-${indicator.damage}`}
          </div>
        ))}

        {/* Game Status Overlay */}
        {gameStatus === "waiting" && (
          <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div className="text-white text-2xl font-bold text-center">
              <div className="mb-4">⏳</div>
              <div>Waiting for players...</div>
              <div className="text-sm mt-2 opacity-75">{Object.keys(players).length} player(s) in arena</div>
            </div>
          </div>
        )}

        {gameStatus === "finished" && (
          <div className="absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center">
            <div className="text-white text-2xl font-bold text-center">
              <div className="mb-4">🏆</div>
              <div>Battle Finished!</div>
              <div className="text-sm mt-2 opacity-75">Check the chat for results</div>
            </div>
          </div>
        )}
      </div>

      {/* Controls Info */}
      <div className="mt-4 text-sm text-gray-400 text-center">
        <div className="mb-2">
          <strong>Movement:</strong> WASD or Arrow Keys
        </div>
        <div className="mb-2">
          <strong>Attack:</strong> Click on enemy then press Space/Enter
        </div>
        {selectedTarget && (
          <div className="text-yellow-400">Target: {getPlayerDisplayName(players[selectedTarget])}</div>
        )}
        {currentPlayer && (
          <div className="mt-2">
            <strong>Your Class:</strong> {currentPlayer.characterClass}
            <span className="ml-2">Range: {CHARACTER_STATS[currentPlayer.characterClass].range}px</span>
          </div>
        )}
      </div>
    </div>
  )
}
