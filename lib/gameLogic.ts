// Game constants
export const GAME_CONFIG = {
  arenaWidth: 800,
  arenaHeight: 600,
  playerSize: 40,
  attackCooldown: 1500, // ms
  moveCooldown: 50, // ms
  damageDisplayDuration: 1000, // ms
}

// Character stats
export const CHARACTER_STATS = {
  warrior: {
    maxHealth: 120,
    damage: 25,
    range: 50,
    speed: 4,
    color: "#e74c3c",
  },
  archer: {
    maxHealth: 80,
    damage: 20,
    range: 150,
    speed: 6,
    color: "#27ae60",
  },
  mage: {
    maxHealth: 70,
    damage: 30,
    range: 100,
    speed: 5,
    color: "#3498db",
  },
}

// Class advantages (damage multipliers)
export const CLASS_ADVANTAGES = {
  warrior: { archer: 1.2, mage: 0.8 },
  archer: { mage: 1.2, warrior: 0.8 },
  mage: { warrior: 1.2, archer: 0.8 },
}

// Key codes for movement
export const KEY_CODES = {
  UP: ["w", "arrowup"],
  DOWN: ["s", "arrowdown"],
  LEFT: ["a", "arrowleft"],
  RIGHT: ["d", "arrowright"],
  ATTACK: [" ", "enter"],
}

// Check if player is alive
export const isPlayerAlive = (player: { health: number; isAlive?: boolean }) => {
  return player.health > 0 && player.isAlive !== false
}

// Calculate health percentage
export const getHealthPercentage = (health: number, maxHealth: number) => {
  return Math.max(0, Math.min(100, (health / maxHealth) * 100))
}

// Get health color based on percentage
export const getHealthColor = (percentage: number) => {
  if (percentage > 60) return "#2ecc71" // Green
  if (percentage > 30) return "#f39c12" // Orange
  return "#e74c3c" // Red
}

// Get player display name
export const getPlayerDisplayName = (player: { characterName: string; username: string }) => {
  return player.characterName || player.username || "Unknown"
}

// Check if target is in attack range
export const isInAttackRange = (
  attacker: { position: { x: number; y: number }; characterClass: string },
  target: { position: { x: number; y: number } },
) => {
  if (!attacker || !target) return false

  const attackerX = attacker.position.x + GAME_CONFIG.playerSize / 2
  const attackerY = attacker.position.y + GAME_CONFIG.playerSize / 2
  const targetX = target.position.x + GAME_CONFIG.playerSize / 2
  const targetY = target.position.y + GAME_CONFIG.playerSize / 2

  const distance = Math.sqrt(Math.pow(targetX - attackerX, 2) + Math.pow(targetY - attackerY, 2))
  const range = CHARACTER_STATS[attacker.characterClass as keyof typeof CHARACTER_STATS].range

  return distance <= range
}

// Calculate damage with class advantages
export const calculateDamage = (
  attacker: { characterClass: string },
  target: { characterClass: string },
  baseDamage: number,
) => {
  const attackerClass = attacker.characterClass as keyof typeof CLASS_ADVANTAGES
  const targetClass = target.characterClass as keyof typeof CLASS_ADVANTAGES

  const multiplier = CLASS_ADVANTAGES[attackerClass]?.[targetClass] || 1
  return Math.round(baseDamage * multiplier)
}

// Clamp position within arena bounds
export const clampPosition = (position: { x: number; y: number }) => {
  return {
    x: Math.max(0, Math.min(GAME_CONFIG.arenaWidth - GAME_CONFIG.playerSize, position.x)),
    y: Math.max(0, Math.min(GAME_CONFIG.arenaHeight - GAME_CONFIG.playerSize, position.y)),
  }
}

// Check if cooldown has passed
export const isCooldownReady = (lastActionTime: number, cooldownMs: number) => {
  return Date.now() - lastActionTime >= cooldownMs
}

// Get remaining cooldown in milliseconds
export const getRemainingCooldown = (lastActionTime: number, cooldownMs: number) => {
  const remaining = cooldownMs - (Date.now() - lastActionTime)
  return Math.max(0, remaining)
}

// Get cooldown percentage (for visual indicators)
export const getCooldownPercentage = (lastActionTime: number, cooldownMs: number) => {
  const remaining = getRemainingCooldown(lastActionTime, cooldownMs)
  return (remaining / cooldownMs) * 100
}

// Generate random position within arena
export const getRandomPosition = () => {
  return {
    x: Math.floor(Math.random() * (GAME_CONFIG.arenaWidth - GAME_CONFIG.playerSize)),
    y: Math.floor(Math.random() * (GAME_CONFIG.arenaHeight - GAME_CONFIG.playerSize)),
  }
}
