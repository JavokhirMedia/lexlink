import { http } from "@inertiajs/react";

/** Small JSON helper over Inertia's HTTP client (handles XSRF). Throws on non-2xx. */
export async function jsonRequest<T>(
    method: "get" | "post",
    url: string,
    data?: unknown,
    signal?: AbortSignal,
): Promise<T> {
    const res = await http.getClient().request({
        method,
        url,
        data,
        signal,
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        },
    });

    if (res.status < 200 || res.status >= 300) {
        throw Object.assign(new Error(`HTTP ${res.status}`), {
            status: res.status,
        });
    }

    return (
        typeof res.data === "string" ? JSON.parse(res.data) : res.data
    ) as T;
}
