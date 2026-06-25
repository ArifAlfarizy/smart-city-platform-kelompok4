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

app.use("/auth/register", authLimiter, makeProxy(services.auth));
app.use("/auth/login", authLimiter, makeProxy(services.auth));
app.use("/oauth/token", authLimiter, makeProxy(services.auth));
app.use("/oauth/revoke", authLimiter, makeProxy(services.auth));

app.use("/api/traffic", limiter, verifyToken, makeProxy(services.traffic));
app.use(
  "/api/environment",
  limiter,
  verifyToken,
  makeProxy(services.environment),
);
app.use("/api/citizens", limiter, verifyToken, makeProxy(services.citizen));
app.use("/api/ml", limiter, verifyToken, makeProxy(services.ml));

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
