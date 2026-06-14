import express from "express";
import { token } from "../controllers/oauthController.js";

const oauthRouter = express.Router();

oauthRouter.post("/token", token);

export default oauthRouter;
