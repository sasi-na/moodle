<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Google Gemini controller for GeniAI.
 *
 * @package   geniaicontroller_gemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace geniaicontroller_gemini;

use dml_exception;
use local_geniai\controller_interface;
use RuntimeException;

/**
 * Sends GeniAI chats to the Gemini generateContent API and returns them in the shape GeniAI expects.
 */
class controller implements controller_interface {

    /**
     * Whether a key is set.
     *
     * @return bool
     */
    public function is_configured() {
        $apikey = (string) get_config("geniaicontroller_gemini", "apikey");
        return isset($apikey[9]);
    }

    /**
     * Generate a completion.
     *
     * @param array $messages Legacy chat messages [role, content].
     * @param string $replacemodel Model override (ignored unless it is a Gemini model).
     * @return array OpenAI-style result with choices[0].message.content.
     */
    public function completions(array $messages, $replacemodel = "") {
        global $DB;

        $apikey = trim((string) get_config("geniaicontroller_gemini", "apikey"));
        $model = trim((string) get_config("geniaicontroller_gemini", "model")) ?: "gemini-3.8-flash";
        if (is_string($replacemodel) && stripos($replacemodel, "gemini") === 0) {
            $model = $replacemodel;
        }

        $system = [];
        $contents = [];
        foreach ($messages as $message) {
            $role = $message["role"] ?? "user";
            $text = trim(strip_tags($message["content"] ?? ""));
            if ($text === "") {
                continue;
            }
            if ($role === "system" || $role === "developer") {
                $system[] = $text;
                continue;
            }
            $grole = $role === "assistant" ? "model" : "user";
            // Gemini wants alternating turns: merge consecutive messages of the same role.
            $last = count($contents) - 1;
            if ($last >= 0 && $contents[$last]["role"] === $grole) {
                $contents[$last]["parts"][0]["text"] .= "\n\n" . $text;
            } else {
                $contents[] = ["role" => $grole, "parts" => [["text" => $text]]];
            }
        }
        if (empty($contents) || $contents[0]["role"] !== "user") {
            array_unshift($contents, ["role" => "user", "parts" => [["text" => "Hello"]]]);
        }

        $post = [
            "contents" => $contents,
            "generationConfig" => ["temperature" => 0.4],
        ];
        if ($system) {
            $post["systemInstruction"] = ["parts" => [["text" => implode("\n\n", $system)]]];
        }

        // Try the chosen model, then a lighter fallback; retry briefly when Google is overloaded (429/503).
        $models = array_values(array_unique([$model, "gemini-3.5-flash-lite"]));
        $data = null;
        foreach ($models as $try) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                [$code, $data, $neterror] = $this->call($apikey, $try, $post);
                if ($neterror !== "") {
                    return ["error" => ["message" => "http error: " . $neterror]];
                }
                if ($code != 429 && $code != 503 && $code != 500) {
                    break 2;
                }
                sleep(2);
            }
        }
        $model = $try;

        if (!is_array($data)) {
            return ["error" => ["message" => "Invalid JSON response from Gemini."]];
        }
        if (!empty($data["error"]["message"])) {
            throw new RuntimeException("Gemini: " . $data["error"]["message"]);
        }

        $texts = [];
        foreach (($data["candidates"][0]["content"]["parts"] ?? []) as $part) {
            if (isset($part["text"]) && empty($part["thought"])) {
                $texts[] = $part["text"];
            }
        }
        $content = trim(implode("\n", $texts));
        $finish = $data["candidates"][0]["finishReason"] ?? "STOP";

        $out = ["id" => $data["responseId"] ?? null, "model" => $model];
        if ($content !== "") {
            $out["choices"] = [["message" => ["role" => "assistant", "content" => $content], "finish_reason" => $finish]];
        } else {
            $reason = $data["promptFeedback"]["blockReason"] ?? $finish;
            $out["error"] = ["message" => "Gemini returned no text ({$reason})."];
        }

        $usage = (object) [
            "send" => json_encode(["endpoint" => "gemini:generateContent", "model" => $model,
                "messagecount" => count($contents)], JSON_PRETTY_PRINT),
            "receive" => json_encode(["id" => $out["id"], "finish_reason" => $finish,
                "usage" => $data["usageMetadata"] ?? null, "error" => $out["error"]["message"] ?? null], JSON_PRETTY_PRINT),
            "model" => $model,
            "prompt_tokens" => (int) ($data["usageMetadata"]["promptTokenCount"] ?? 0),
            "completion_tokens" => (int) ($data["usageMetadata"]["candidatesTokenCount"] ?? 0),
            "timecreated" => time(),
            "datecreated" => date("Y-m-d"),
        ];
        try {
            $DB->insert_record("local_geniai_usage", $usage);
        } catch (dml_exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }

        return $out;
    }

    /**
     * One HTTP call to generateContent.
     *
     * @param string $apikey
     * @param string $model
     * @param array $post
     * @return array [http code, decoded body or null, network error]
     */
    private function call($apikey, $model, array $post) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model) . ":generateContent";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "x-goog-api-key: {$apikey}",
        ]);
        $result = curl_exec($ch);
        $error = curl_errno($ch) ? curl_error($ch) : "";
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, json_decode((string) $result, true), $error];
    }
}
