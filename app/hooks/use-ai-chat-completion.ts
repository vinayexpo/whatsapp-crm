import { useCallback, useState } from "react";
import type { AiAssistantSettings } from "~/data/types";
import { apiClient } from "~/utils/api-client";

export interface ChatCompletionMessage {
  role: "system" | "user" | "assistant";
  content: string;
}

/**
 * Sends a chat completion request through the backend, which proxies to the
 * OpenAI-compatible provider configured in Settings. Calling the provider
 * directly from the browser would fail on CORS and would expose the API key.
 */
export function useAiChatCompletion(settings: AiAssistantSettings) {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isConfigured = Boolean(settings.baseUrl.trim() && settings.apiKey?.trim() && settings.model.trim());

  const sendChatCompletion = useCallback(
    async (messages: ChatCompletionMessage[]): Promise<string> => {
      if (!isConfigured) {
        const message = "AI Assistant isn't configured yet. Add your Base API URL, API key, and model in Settings.";
        setError(message);
        throw new Error(message);
      }

      setIsLoading(true);
      setError(null);
      try {
        return await apiClient.sendAiAssistantChat(messages);
      } catch (err) {
        const message = err instanceof Error ? err.message : "Something went wrong contacting the AI Assistant.";
        setError(message);
        throw new Error(message);
      } finally {
        setIsLoading(false);
      }
    },
    [isConfigured],
  );

  return { sendChatCompletion, isLoading, error, isConfigured };
}
