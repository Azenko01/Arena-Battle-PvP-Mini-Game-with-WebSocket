"use client"

import { useState } from "react"
import { GameProvider } from "@/context/GameContext"
import { GameInterface } from "@/components/game-interface"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Sword, Shield, Zap } from "lucide-react"

interface PlayerData {
  playerId: string
  username: string
  characterName: string
  characterClass: "warrior" | "archer" | "mage"
}

export default function HomePage() {
  const [gameStarted, setGameStarted] = useState(false)
  const [battleId, setBattleId] = useState("")
  const [playerData, setPlayerData] = useState<PlayerData>({
    playerId: "",
    username: "",
    characterName: "",
    characterClass: "warrior",
  })

  const handleStartGame = () => {
    if (!battleId.trim() || !playerData.username.trim() || !playerData.characterName.trim()) {
      alert("Please fill in all fields")
      return
    }

    // Generate unique player ID
    const playerId = `player_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`

    setPlayerData((prev) => ({ ...prev, playerId }))
    setGameStarted(true)
  }

  const getClassIcon = (characterClass: string) => {
    switch (characterClass) {
      case "warrior":
        return <Sword className="w-5 h-5" />
      case "archer":
        return <Shield className="w-5 h-5" />
      case "mage":
        return <Zap className="w-5 h-5" />
      default:
        return <Sword className="w-5 h-5" />
    }
  }

  const getClassDescription = (characterClass: string) => {
    switch (characterClass) {
      case "warrior":
        return "High health, close combat specialist"
      case "archer":
        return "Long range attacks, high mobility"
      case "mage":
        return "High damage magic attacks"
      default:
        return ""
    }
  }

  if (gameStarted) {
    return (
      <GameProvider>
        <GameInterface battleId={battleId} playerData={playerData} />
      </GameProvider>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-orange-900 flex items-center justify-center p-4">
      <Card className="w-full max-w-md bg-gray-800 border-orange-500">
        <CardHeader className="text-center">
          <CardTitle className="text-3xl font-bold text-orange-500 mb-2">🏟️ Arena Battle</CardTitle>
          <p className="text-gray-400">Enter the arena and fight for glory!</p>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Battle ID */}
          <div className="space-y-2">
            <Label htmlFor="battleId" className="text-white">
              Battle ID
            </Label>
            <Input
              id="battleId"
              value={battleId}
              onChange={(e) => setBattleId(e.target.value)}
              placeholder="Enter battle ID (e.g., battle_001)"
              className="bg-gray-700 border-gray-600 text-white"
            />
          </div>

          {/* Username */}
          <div className="space-y-2">
            <Label htmlFor="username" className="text-white">
              Username
            </Label>
            <Input
              id="username"
              value={playerData.username}
              onChange={(e) => setPlayerData((prev) => ({ ...prev, username: e.target.value }))}
              placeholder="Your username"
              className="bg-gray-700 border-gray-600 text-white"
            />
          </div>

          {/* Character Name */}
          <div className="space-y-2">
            <Label htmlFor="characterName" className="text-white">
              Character Name
            </Label>
            <Input
              id="characterName"
              value={playerData.characterName}
              onChange={(e) => setPlayerData((prev) => ({ ...prev, characterName: e.target.value }))}
              placeholder="Your character's name"
              className="bg-gray-700 border-gray-600 text-white"
            />
          </div>

          {/* Character Class */}
          <div className="space-y-2">
            <Label className="text-white">Character Class</Label>
            <Select
              value={playerData.characterClass}
              onValueChange={(value: "warrior" | "archer" | "mage") =>
                setPlayerData((prev) => ({ ...prev, characterClass: value }))
              }
            >
              <SelectTrigger className="bg-gray-700 border-gray-600 text-white">
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-gray-700 border-gray-600">
                <SelectItem value="warrior" className="text-white">
                  <div className="flex items-center gap-2">
                    <Sword className="w-4 h-4 text-red-500" />
                    <div>
                      <div className="font-semibold">⚔️ Warrior</div>
                      <div className="text-xs text-gray-400">High health, close combat</div>
                    </div>
                  </div>
                </SelectItem>
                <SelectItem value="archer" className="text-white">
                  <div className="flex items-center gap-2">
                    <Shield className="w-4 h-4 text-green-500" />
                    <div>
                      <div className="font-semibold">🏹 Archer</div>
                      <div className="text-xs text-gray-400">Long range, high mobility</div>
                    </div>
                  </div>
                </SelectItem>
                <SelectItem value="mage" className="text-white">
                  <div className="flex items-center gap-2">
                    <Zap className="w-4 h-4 text-blue-500" />
                    <div>
                      <div className="font-semibold">🔮 Mage</div>
                      <div className="text-xs text-gray-400">High damage magic</div>
                    </div>
                  </div>
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Class Info */}
          <div className="bg-gray-700 p-3 rounded-lg">
            <div className="flex items-center gap-2 mb-2">
              {getClassIcon(playerData.characterClass)}
              <span className="font-semibold text-white capitalize">{playerData.characterClass}</span>
            </div>
            <p className="text-sm text-gray-300">{getClassDescription(playerData.characterClass)}</p>
          </div>

          {/* Start Game Button */}
          <Button
            onClick={handleStartGame}
            className="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-3"
            size="lg"
          >
            🚀 Enter Arena
          </Button>

          {/* Instructions */}
          <div className="text-xs text-gray-400 text-center space-y-1">
            <p>• Use WASD or arrow keys to move</p>
            <p>• Click on enemies to target them</p>
            <p>• Press Space/Enter to attack</p>
            <p>• Chat with other players during battle</p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
