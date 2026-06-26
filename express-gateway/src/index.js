import express from "express";
import "dotenv/config";
import verifyToken from "./middleware/verifyToken.js";
import { limiter, authLimiter } from "./middleware/rateLimit.js";
import { requestLogger } from "./middleware/logger.js";
import { makeProxy } from "./middleware/serviceProxy.js";
import { cors } from "./middleware/cors.js";
import dashboardRouter from "./routes/dashboard.js";
import { services, GATEWAY_PORT } from "./config/services.js";

const app = express();

app.use(cors);
app.use(requestLogger);

app.get("/health", (req, res) => {
  res.status(200).json({
    status: "success",
    service: "api-gateway",
    timestamp: new Date().toISOString(),
  });
});

// Auth endpoints (public)
app.use("/auth/register", authLimiter, makeProxy(services.auth));
app.use("/auth/login", authLimiter, makeProxy(services.auth));
app.use("/oauth/token", authLimiter, makeProxy(services.auth));
app.use("/oauth/revoke", authLimiter, makeProxy(services.auth));
app.use("/oauth/introspect", authLimiter, makeProxy(services.auth));

// Traffic endpoints (protected) 
app.use("/api/traffic", limiter, verifyToken, makeProxy(services.traffic));
app.use("/api/traffic-status", limiter, verifyToken, makeProxy(services.traffic));
app.use("/api/traffic-data", limiter, verifyToken, makeProxy(services.traffic));
app.use("/api/traffic-history", limiter, verifyToken, makeProxy(services.traffic));
app.use("/api/traffic-summary", limiter, verifyToken, makeProxy(services.traffic));

// Environment endpoints (protected)
app.use("/api/environment", limiter, verifyToken, makeProxy(services.environment));

// Citizen endpoints (protected)
app.use("/api/citizens", limiter, verifyToken, makeProxy(services.citizen));

// ML endpoints (protected)
app.use("/api/ml", limiter, verifyToken, makeProxy(services.ml));

// Dashboard
app.use("/api/dashboard", limiter, verifyToken, dashboardRouter);

// 404 fallback
app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: "Endpoint tidak ditemukan di API Gateway.",
  });
});

app.listen(GATEWAY_PORT, () => {
  console.log(`API Gateway running on port ${GATEWAY_PORT}`);
});