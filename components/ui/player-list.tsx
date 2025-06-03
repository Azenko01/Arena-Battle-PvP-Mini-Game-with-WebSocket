"use client"

import type React from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import { Crown, Skull, Sword, Target } from "lucide-react"
import type { Player } from "@/hooks/useWebSocket"
import { CHARACTER_STATS, getHealthPercentage, getHealthColor, isPlayerAlive } from "@/lib/gameLogic"

interface PlayerListProps {
  players: Record<string, Player>
  currentPlayerId: string | null
}

export const PlayerList: React.FC<PlayerListProps> = ({ players, currentPlayerId }) => {
  const playerArray = Object.values(players)
  const alivePlayers = playerArray.filter(isPlayerAlive)
  const deadPlayers = playerArray.filter((p) => !isPlayerAlive(p))

  const getClassIcon = (characterClass: Player["characterClass"]) => {
    switch (characterClass) {
      case "warrior":
        return "⚔️"
      case "archer":
        return "🏹"
      case "mage":
        return "🔮"
      default:
        return "❓"
    }
  }

  const getClassColor = (characterClass: Player["characterClass"]) => {
    return CHARACTER_STATS[characterClass].color
  }

  const PlayerCard: React.FC<{ player: Player; isCurrentPlayer: boolean }> = ({ player, isCurrentPlayer }) => {
    const healthPercentage = getHealthPercentage(player.health, player.maxHealth)
    const healthColor = getHealthColor(healthPercentage)
    const isAlive = isPlayerAlive(player)

    return (
      <Card
        className={`mb-2 ${isCurrentPlayer ? "border-orange-500 bg-orange-950/20" : "border-gray-700"} ${!isAlive ? "opacity-60" : ""}`}
      >
        <CardContent className="p-3">
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-2">
              {isCurrentPlayer && <Crown className="w-4 h-4 text-orange-500" />}
              {!isAlive && <Skull className="w-4 h-4 text-red-500" />}
              <span className="font-semibold text-sm">{player.characterName}</span>
            </div>
            <div className="flex items-center gap-1">
              <span className="text-lg">{getClassIcon(player.characterClass)}</span>
              <Badge
                variant="outline"
                className="text-xs"
                style={{ borderColor: getClassColor(player.characterClass) }}
              >
                {player.characterClass}
              </Badge>
            </div>
          </div>

          <div className="text-xs text-gray-400 mb-2">@{player.username}</div>

          {/* Health Bar */}
          <div className="space-y-1">
            <div className="flex justify-between text-xs">
              <span>Health</span>
              <span style={{ color: healthColor }}>
                {player.health}/{player.maxHealth}
              </span>
            </div>
            <Progress
              value={healthPercentage}
              className="h-2"
              style={{
                backgroundColor: "rgba(255,255,255,0.1)",
              }}
            />
          </div>

          {/* Position Info */}
          <div className="text-xs text-gray-500 mt-2">
            Position: ({Math.round(player.position.x)}, {Math.round(player.position.y)})
          </div>

          {/* Status Indicators */}
          <div className="flex gap-1 mt-2">
            {isAlive ? (
              <Badge variant="outline" className="text-xs text-green-400 border-green-400">
                Alive
              </Badge>
            ) : (
              <Badge variant="outline" className="text-xs text-red-400 border-red-400">
                Dead
              </Badge>
            )}

            {isCurrentPlayer && (
              <Badge variant="outline" className="text-xs text-orange-400 border-orange-400">
                You
              </Badge>
            )}
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="h-full">
      <Card className="h-full">
        <CardHeader className="pb-3">
          <CardTitle className="text-lg flex items-center gap-2">
            <Target className="w-5 h-5" />
            Players ({playerArray.length})
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <div className="px-4 pb-4 max-h-[calc(100vh-200px)] overflow-y-auto">
            {playerArray.length === 0 ? (
              <div className="text-center text-gray-500 py-8">
                <div className="text-4xl mb-2">👥</div>
                <div>No players in battle</div>
              </div>
            ) : (
              <>
                {/* Alive Players */}
                {alivePlayers.length > 0 && (
                  <div className="mb-4">
                    <div className="text-sm font-semibold text-green-400 mb-2 flex items-center gap-2">
                      <Sword className="w-4 h-4" />
                      Alive ({alivePlayers.length})
                    </div>
                    {alivePlayers.map((player) => (
                      <PlayerCard key={player.id} player={player} isCurrentPlayer={player.id === currentPlayerId} />
                    ))}
                  </div>
                )}

                {/* Dead Players */}
                {deadPlayers.length > 0 && (
                  <div>
                    <div className="text-sm font-semibold text-red-400 mb-2 flex items-center gap-2">
                      <Skull className="w-4 h-4" />
                      Eliminated ({deadPlayers.length})
                    </div>
                    {deadPlayers.map((player) => (
                      <PlayerCard key={player.id} player={player} isCurrentPlayer={player.id === currentPlayerId} />
                    ))}
                  </div>
                )}
              </>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
