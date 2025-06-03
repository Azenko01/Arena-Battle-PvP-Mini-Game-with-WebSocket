"use client"

import type React from "react"
import { createContext, useContext } from "react"
import { useWebSocket, type GameState } from "@/hooks/useWebSocket"

interface GameContextType {
  gameState: GameState
  connectionStatus: "connecting" | "connected" | "disconnected" | "error"
  joinBattle: (
    battleId: string,
    playerData: {
      playerId: string
      username: string
      characterName: string
      characterClass: string
    },
  ) => void
  movePlayer: (position: { x: number; y: number }) => void
  attackPlayer: (targetId: string) => void
  sendChatMessage: (message: string) => void
  disconnect: () => void
}

const GameContext = createContext<GameContextType | null>(null)

interface GameProviderProps {
  children: React.ReactNode
  wsUrl?: string
}

export const GameProvider: React.FC<GameProviderProps> = ({ children, wsUrl = "ws://localhost:8080" }) => {
  const webSocket = useWebSocket(wsUrl)

  return <GameContext.Provider value={webSocket}>{children}</GameContext.Provider>
}

export const useGame = () => {
  const context = useContext(GameContext)
  if (!context) {
    throw new Error("useGame must be used within a GameProvider")
  }
  return context
}
