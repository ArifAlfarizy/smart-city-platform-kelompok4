import rateLimit from "express-rate-limit";

// Global rate limiter
export const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: {
    status: "error",
    code: 429,
    message: "Too many requests. Try again later.",
  },
});

export const authLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: {
    status: "error",
    code: 429,
    message: "Too many requests. Try again later.",
  },
});
