"use client"

import type React from "react"
import { useState, useEffect } from "react"
import { useWebSocket } from "@/hooks/useWebSocket"
import { Arena } from "@/components/Arena"
import { Chat } from "@/components/ui/chat"
import { PlayerList } from "@/components/ui/player-list"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { AlertCircle, Wifi, WifiOff } from "lucide-react"

interface GameInterfaceProps {
  battleId: string
  playerData: {
    playerId: string
    username: string
    characterName: string
    characterClass: string
  }
}

export const GameInterface: React.FC<GameInterfaceProps> = ({ battleId, playerData }) => {
  const { gameState, connectionStatus, joinBattle, movePlayer, attackPlayer, sendChatMessage } =
    useWebSocket("ws://localhost:8080")

  const [hasJoined, setHasJoined] = useState(false)

  // Join battle when connected
  useEffect(() => {
    if (connectionStatus === "connected" && !hasJoined) {
      joinBattle(battleId, playerData)
      setHasJoined(true)
    }
  }, [connectionStatus, hasJoined, battleId, playerData, joinBattle])

  const getStatusColor = () => {
    switch (gameState.gameStatus) {
      case "waiting":
        return "bg-yellow-500"
      case "active":
        return "bg-green-500"
      case "finished":
        return "bg-gray-500"
      default:
        return "bg-gray-500"
    }
  }

  const getStatusText = () => {
    switch (gameState.gameStatus) {
      case "waiting":
        return "Waiting for players..."
      case "active":
        return "Battle in progress!"
      case "finished":
        return "Battle finished"
      default:
        return "Unknown status"
    }
  }

  const getConnectionIcon = () => {
    switch (connectionStatus) {
      case "connected":
        return <Wifi className="w-4 h-4 text-green-500" />
      case "connecting":
        return <Wifi className="w-4 h-4 text-yellow-500" />
      default:
        return <WifiOff className="w-4 h-4 text-red-500" />
    }
  }

  if (connectionStatus === "error") {
    return (
      <div className="min-h-screen bg-gray-900 flex items-center justify-center">
        <Card className="w-96">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-red-500">
              <AlertCircle className="w-5 h-5" />
              Connection Error
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-gray-400 mb-4">
              Failed to connect to the game server. Please check if the WebSocket server is running.
            </p>
            <Button onClick={() => window.location.reload()} className="w-full">
              Retry Connection
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-900 text-white">
      {/* Header */}
      <div className="border-b border-gray-700 p-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-orange-500">Arena Battle</h1>
            <p className="text-gray-400">Battle ID: {battleId}</p>
          </div>

          <div className="flex items-center gap-4">
            <Badge className={getStatusColor()}>{getStatusText()}</Badge>

            <div className="flex items-center gap-2">
              {getConnectionIcon()}
              <span className="text-sm text-gray-400 capitalize">{connectionStatus}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Main Game Area */}
      <div className="flex h-[calc(100vh-80px)]">
        {/* Left Sidebar - Player List */}
        <div className="w-80 border-r border-gray-700 p-4">
          <PlayerList players={gameState.players} currentPlayerId={gameState.currentPlayerId} />
        </div>

        {/* Center - Arena */}
        <div className="flex-1 p-4 flex items-center justify-center">
          <Arena
            players={gameState.players}
            currentPlayerId={gameState.currentPlayerId}
            onMove={movePlayer}
            onAttack={attackPlayer}
            gameStatus={gameState.gameStatus}
          />
        </div>

        {/* Right Sidebar - Chat */}
        <div className="w-80 border-l border-gray-700">
          <Card className="h-full rounded-none border-0">
            <CardHeader className="pb-3">
              <CardTitle className="text-lg">Chat</CardTitle>
            </CardHeader>
            <CardContent className="p-0 h-[calc(100%-80px)]">
              <Chat
                messages={gameState.chatMessages}
                onSendMessage={sendChatMessage}
                disabled={connectionStatus !== "connected"}
              />
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
