import { defineConfig } from '@playwright/test';
export default defineConfig({testDir:'browser-tests',timeout:45000,workers:1,reporter:[['list']],use:{browserName:'chromium',headless:true,launchOptions:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH?{executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH}:{}}});
