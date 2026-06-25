import express from "express";
import "dotenv/config";
import pool from "./configs/db.js";
import authRouter from "./routes/authRouter.js";
import cookieParser from "cookie-parser";
import oauthRouter from "./routes/oauthRouter.js";
import { timeStamp } from "console";

const PORT = process.env.PORT || 3001;

const app = express();
app.use(express.json());
app.use(cookieParser());

app.use("/auth", authRouter);
app.use("/oauth", oauthRouter);

app.get("/health", async (req, res) => {
  try {
    await pool.query("SELECT 1");
    return res.status(200).json({
      status: "success",
      service: "auth-service",
      db_connected: true,
      timeStamp: new Date().toISOString(),
    });
  } catch (err) {
    return res.status(500).json({
      status: "error",
      service: "auth-service",
      db_connected: false,
      timeStamp: new Date().toISOString,
    });
  }
});

async function startServer() {
  try {
    await pool.query("SELECT 1");

    console.log("Database connected");

    app.listen(PORT, () => {
      console.log(`OAuth server running on PORT ${PORT}`);
    });
  } catch (err) {
    console.error("Database connection failed:");
    console.error(err.message);
    process.exit(1);
  }
}

startServer();
