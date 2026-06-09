import express from "express";
import "dotenv/config";
import pool from "./configs/db.js";
import authRouter from "./routes/authRouter.js";

const PORT = process.env.PORT || 3002;

const app = express();
app.use(express.json());

app.use("/auth", authRouter);

app.get("/", (req, res) => {
  res.send("test user index server");
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
