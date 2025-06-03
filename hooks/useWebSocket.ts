"use client"

import { useState, useEffect, useRef, useCallback } from "react"

// Player interface
export interface Player {
  id: string
  username: string
  characterName: string
  characterClass: "warrior" | "archer" | "mage"
  position: { x: number; y: number }
  health: number
  maxHealth: number
  isAlive: boolean
  lastAction: number
  token?: string // For authentication
}

// Game state interface
export interface GameState {
  battleId: string | null
  players: Record<string, Player>
  currentPlayerId: string | null
  gameStatus: "waiting" | "active" | "finished"
  winner: string | null
  chatMessages: ChatMessage[]
}

// Chat message interface
export interface ChatMessage {
  id: string
  playerId: string
  username: string
  message: string
  timestamp: number
  type: "chat" | "system"
}

// WebSocket message interface
export interface WebSocketMessage {
  type:
    | "init"
    | "player_joined"
    | "player_left"
    | "player_moved"
    | "player_attacked"
    | "player_died"
    | "battle_started"
    | "battle_ended"
    | "chat_message"
    | "game_state"
    | "heartbeat_response"
    | "error"
  data?: any
  playerId?: string
  battleId?: string
  position?: { x: number; y: number }
  targetId?: string
  damage?: number
  health?: number
  message?: string
  username?: string
  timestamp?: number
  winner?: string
  players?: Player[]
  gameStatus?: string
  token?: string // For authentication
}

/**
 * Custom hook for WebSocket connection and game state management
 * @param url WebSocket server URL
 * @returns Game state and WebSocket methods
 */
export const useWebSocket = (url: string) => {
  // Game state
  const [gameState, setGameState] = useState<GameState>({
    battleId: null,
    players: {},
    currentPlayerId: null,
    gameStatus: "waiting",
    winner: null,
    chatMessages: [],
  })

  // Connection status
  const [connectionStatus, setConnectionStatus] = useState<"connecting" | "connected" | "disconnected" | "error">(
    "disconnected",
  )

  // WebSocket reference
  const wsRef = useRef<WebSocket | null>(null)
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null)
  const heartbeatIntervalRef = useRef<NodeJS.Timeout | null>(null)
  const authTokenRef = useRef<string | null>(null)

  /**
   * Connect to WebSocket server
   */
  const connect = useCallback(() => {
    // Don't reconnect if already connected
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      return
    }

    setConnectionStatus("connecting")

    try {
      // Create WebSocket connection
      wsRef.current = new WebSocket(url)

      // Connection opened
      wsRef.current.onopen = () => {
        console.log("WebSocket connected")
        setConnectionStatus("connected")

        // Start heartbeat to keep connection alive
        heartbeatIntervalRef.current = setInterval(() => {
          if (wsRef.current?.readyState === WebSocket.OPEN) {
            wsRef.current.send(JSON.stringify({ type: "heartbeat" }))
          }
        }, 30000) // Every 30 seconds
      }

      // Message received
      wsRef.current.onmessage = (event) => {
        try {
          const message: WebSocketMessage = JSON.parse(event.data)
          handleMessage(message)
        } catch (error) {
          console.error("Failed to parse WebSocket message:", error)
        }
      }

      // Connection closed
      wsRef.current.onclose = () => {
        console.log("WebSocket disconnected")
        setConnectionStatus("disconnected")

        // Clear heartbeat
        if (heartbeatIntervalRef.current) {
          clearInterval(heartbeatIntervalRef.current)
          heartbeatIntervalRef.current = null
        }

        // Attempt to reconnect after 3 seconds
        reconnectTimeoutRef.current = setTimeout(() => {
          connect()
        }, 3000)
      }

      // Connection error
      wsRef.current.onerror = (error) => {
        console.error("WebSocket error:", error)
        setConnectionStatus("error")
      }
    } catch (error) {
      console.error("Failed to create WebSocket connection:", error)
      setConnectionStatus("error")
    }
  }, [url])

  /**
   * Handle incoming WebSocket messages
   * @param message WebSocket message
   */
  const handleMessage = useCallback(
    (message: WebSocketMessage) => {
      switch (message.type) {
        // Initial game state or full state update
        case "init":
        case "game_state":
          setGameState((prev) => ({
            ...prev,
            battleId: message.battleId || prev.battleId,
            players: message.players
              ? message.players.reduce((acc, player) => ({ ...acc, [player.id]: player }), {})
              : prev.players,
            gameStatus: (message.gameStatus as any) || prev.gameStatus,
          }))
          break

        // New player joined
        case "player_joined":
          if (message.data) {
            setGameState((prev) => ({
              ...prev,
              players: {
                ...prev.players,
                [message.data.id]: message.data,
              },
            }))

            addSystemMessage(`${message.data.characterName} joined the battle!`)
          }
          break

        // Player left
        case "player_left":
          if (message.playerId) {
            setGameState((prev) => {
              const newPlayers = { ...prev.players }
              const playerName = newPlayers[message.playerId!]?.characterName || "Unknown"
              delete newPlayers[message.playerId!]

              return {
                ...prev,
                players: newPlayers,
              }
            })

            addSystemMessage(`Player left the battle.`)
          }
          break

        // Player moved
        case "player_moved":
          if (message.playerId && message.position) {
            setGameState((prev) => ({
              ...prev,
              players: {
                ...prev.players,
                [message.playerId!]: {
                  ...prev.players[message.playerId!],
                  position: message.position!,
                  lastAction: Date.now(),
                },
              },
            }))
          }
          break

        // Player attacked
        case "player_attacked":
          if (message.data) {
            const { attackerId, targetId, damage, targetHealth } = message.data

            setGameState((prev) => ({
              ...prev,
              players: {
                ...prev.players,
                [targetId]: {
                  ...prev.players[targetId],
                  health: targetHealth,
                },
              },
            }))

            const attacker = gameState.players[attackerId]
            const target = gameState.players[targetId]
            if (attacker && target) {
              addSystemMessage(`${attacker.characterName} attacked ${target.characterName} for ${damage} damage!`)
            }
          }
          break

        // Player died
        case "player_died":
          if (message.data) {
            const { playerId, killerId } = message.data

            setGameState((prev) => ({
              ...prev,
              players: {
                ...prev.players,
                [playerId]: {
                  ...prev.players[playerId],
                  health: 0,
                  isAlive: false,
                },
              },
            }))

            const player = gameState.players[playerId]
            const killer = gameState.players[killerId]
            if (player && killer) {
              addSystemMessage(`${player.characterName} was defeated by ${killer.characterName}!`)
            }
          }
          break

        // Battle started
        case "battle_started":
          setGameState((prev) => ({
            ...prev,
            gameStatus: "active",
          }))
          addSystemMessage("Battle has started! Fight for victory!")
          break

        // Battle ended
        case "battle_ended":
          setGameState((prev) => ({
            ...prev,
            gameStatus: "finished",
            winner: message.winner || null,
          }))

          if (message.winner) {
            const winner = gameState.players[message.winner]
            addSystemMessage(`🏆 ${winner?.characterName || "Unknown"} wins the battle!`)
          } else {
            addSystemMessage("Battle ended with no winner.")
          }
          break

        // Chat message
        case "chat_message":
          if (message.data) {
            addChatMessage({
              id: Date.now().toString(),
              playerId: message.data.playerId,
              username: message.data.username,
              message: message.data.message,
              timestamp: message.data.timestamp || Date.now(),
              type: "chat",
            })
          }
          break

        // Heartbeat response (keep-alive)
        case "heartbeat_response":
          // Keep connection alive
          break

        // Error message
        case "error":
          console.error("WebSocket error:", message.data)
          break

        // Unknown message type
        default:
          console.warn("Unknown message type:", message.type)
      }
    },
    [gameState.players],
  )

  /**
   * Add chat message to game state
   * @param message Chat message
   */
  const addChatMessage = useCallback((message: ChatMessage) => {
    setGameState((prev) => ({
      ...prev,
      chatMessages: [...prev.chatMessages.slice(-49), message], // Keep last 50 messages
    }))
  }, [])

  /**
   * Add system message to chat
   * @param text System message text
   */
  const addSystemMessage = useCallback(
    (text: string) => {
      addChatMessage({
        id: Date.now().toString(),
        playerId: "system",
        username: "System",
        message: text,
        timestamp: Date.now(),
        type: "system",
      })
    },
    [addChatMessage],
  )

  /**
   * Send message to WebSocket server
   * @param message WebSocket message
   */
  const sendMessage = useCallback((message: Omit<WebSocketMessage, "timestamp">) => {
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      // Add authentication token if available
      const messageWithAuth = authTokenRef.current
        ? {
            ...message,
            token: authTokenRef.current,
            timestamp: Date.now(),
          }
        : {
            ...message,
            timestamp: Date.now(),
          }

      wsRef.current.send(JSON.stringify(messageWithAuth))
    } else {
      console.warn("WebSocket is not connected")
    }
  }, [])

  /**
   * Join battle
   * @param battleId Battle ID
   * @param playerData Player data
   */
  const joinBattle = useCallback(
    (
      battleId: string,
      playerData: {
        playerId: string
        username: string
        characterName: string
        characterClass: string
        token?: string // For authentication
      },
    ) => {
      // Store authentication token
      if (playerData.token) {
        authTokenRef.current = playerData.token
      }

      setGameState((prev) => ({
        ...prev,
        battleId,
        currentPlayerId: playerData.playerId,
      }))

      sendMessage({
        type: "init",
        battleId,
        data: playerData,
      })
    },
    [sendMessage],
  )

  /**
   * Move player
   * @param position New position
   */
  const movePlayer = useCallback(
    (position: { x: number; y: number }) => {
      sendMessage({
        type: "player_moved",
        position,
      })
    },
    [sendMessage],
  )

  /**
   * Attack player
   * @param targetId Target player ID
   */
  const attackPlayer = useCallback(
    (targetId: string) => {
      sendMessage({
        type: "player_attacked",
        targetId,
      })
    },
    [sendMessage],
  )

  /**
   * Send chat message
   * @param message Chat message
   */
  const sendChatMessage = useCallback(
    (message: string) => {
      sendMessage({
        type: "chat_message",
        data: { message },
      })
    },
    [sendMessage],
  )

  /**
   * Disconnect from WebSocket server
   */
  const disconnect = useCallback(() => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current)
      reconnectTimeoutRef.current = null
    }

    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current)
      heartbeatIntervalRef.current = null
    }

    if (wsRef.current) {
      wsRef.current.close()
      wsRef.current = null
    }

    setConnectionStatus("disconnected")
  }, [])

  // Connect on mount, disconnect on unmount
  useEffect(() => {
    connect()

    return () => {
      disconnect()
    }
  }, [connect, disconnect])

  return {
    gameState,
    connectionStatus,
    connect,
    disconnect,
    joinBattle,
    movePlayer,
    attackPlayer,
    sendChatMessage,
  }
}
