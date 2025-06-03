"use client"

import type React from "react"
import { useState, useRef, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Send } from "lucide-react"
import type { ChatMessage } from "@/hooks/useWebSocket"

interface ChatProps {
  messages: ChatMessage[]
  onSendMessage: (message: string) => void
  disabled?: boolean
}

export const Chat: React.FC<ChatProps> = ({ messages, onSendMessage, disabled = false }) => {
  const [inputValue, setInputValue] = useState("")
  const scrollAreaRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  // Auto-scroll to bottom when new messages arrive
  useEffect(() => {
    if (scrollAreaRef.current) {
      const scrollContainer = scrollAreaRef.current.querySelector("[data-radix-scroll-area-viewport]")
      if (scrollContainer) {
        scrollContainer.scrollTop = scrollContainer.scrollHeight
      }
    }
  }, [messages])

  const handleSend = () => {
    if (inputValue.trim() && !disabled) {
      onSendMessage(inputValue.trim())
      setInputValue("")
      inputRef.current?.focus()
    }
  }

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  const formatTime = (timestamp: number) => {
    const date = new Date(timestamp)
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
  }

  const getMessageStyle = (message: ChatMessage) => {
    if (message.type === "system") {
      return "text-yellow-400 italic text-center"
    }
    return "text-white"
  }

  const getMessagePrefix = (message: ChatMessage) => {
    if (message.type === "system") {
      return "🔔 "
    }
    return ""
  }

  return (
    <div className="flex flex-col h-full">
      {/* Messages Area */}
      <ScrollArea ref={scrollAreaRef} className="flex-1 p-4">
        <div className="space-y-2">
          {messages.length === 0 ? (
            <div className="text-gray-500 text-center text-sm">No messages yet. Start chatting!</div>
          ) : (
            messages.map((message) => (
              <div key={message.id} className={`text-sm ${getMessageStyle(message)}`}>
                <div className="flex items-start gap-2">
                  <span className="text-gray-400 text-xs min-w-[45px]">{formatTime(message.timestamp)}</span>
                  <div className="flex-1">
                    {message.type === "system" ? (
                      <div className="text-yellow-400 italic">
                        {getMessagePrefix(message)}
                        {message.message}
                      </div>
                    ) : (
                      <>
                        <span className="font-semibold text-blue-400">{message.username}:</span>
                        <span className="ml-2">{message.message}</span>
                      </>
                    )}
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      </ScrollArea>

      {/* Input Area */}
      <div className="border-t border-gray-700 p-4">
        <div className="flex gap-2">
          <Input
            ref={inputRef}
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            onKeyPress={handleKeyPress}
            placeholder={disabled ? "Connecting..." : "Type a message..."}
            disabled={disabled}
            className="flex-1 bg-gray-800 border-gray-600 text-white placeholder-gray-400"
            maxLength={200}
          />
          <Button
            onClick={handleSend}
            disabled={disabled || !inputValue.trim()}
            size="icon"
            className="bg-orange-600 hover:bg-orange-700"
          >
            <Send className="w-4 h-4" />
          </Button>
        </div>
        <div className="text-xs text-gray-500 mt-1">Press Enter to send • {inputValue.length}/200</div>
      </div>
    </div>
  )
}
