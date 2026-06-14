import express from "express";
import { passwordGrant } from "../controllers/oauthController.js";

const oauthRouter = express.Router();

oauthRouter.post("/token", passwordGrant);

export default oauthRouter;
