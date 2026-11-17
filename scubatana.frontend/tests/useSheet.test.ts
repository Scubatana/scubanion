import { mountSuspended } from "@nuxt/test-utils/runtime";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h } from "vue";

const { backButton } = vi.hoisted(() => ({
	backButton: {
		handler: null as (() => void) | null,
		added: 0,
		removed: 0
	}
}));

vi.mock("@capacitor/app", () => ({
	App: {
		addListener: async (event: string, handler: () => void) => {
			if (event === "backButton") {
				backButton.handler = handler;
				backButton.added++;
			}

			return {
				remove: async () => {
					backButton.handler = null;
					backButton.removed++;
				}
			};
		}
	}
}));

function sheet(onClose: () => void) {
	return defineComponent({
		setup() {
			useSheet(onClose);

			return () => h("div");
		}
	});
}

function pressBack(): void {
	backButton.handler?.();
}

describe("useSheet", () => {
	beforeEach(() => {
		backButton.handler = null;
		backButton.added = 0;
		backButton.removed = 0;
	});

	it("closes only the sheet on top with the back button and Escape", async () => {
		const below = vi.fn();
		const above = vi.fn();
		const first = await mountSuspended(sheet(below));
		const second = await mountSuspended(sheet(above));

		pressBack();
		window.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));

		expect(above).toHaveBeenCalledTimes(2);
		expect(below).not.toHaveBeenCalled();

		second.unmount();
		pressBack();

		expect(below).toHaveBeenCalledTimes(1);
		first.unmount();
	});

	it("listens to the back button only while a sheet is open, so Android keeps its usual back", async () => {
		const first = await mountSuspended(sheet(vi.fn()));
		const second = await mountSuspended(sheet(vi.fn()));

		expect(backButton.added).toBe(1);

		second.unmount();
		expect(backButton.removed).toBe(0);

		first.unmount();
		await Promise.resolve();
		await Promise.resolve();

		expect(backButton.removed).toBe(1);
		expect(backButton.handler).toBeNull();
	});

	it("ignores other keys", async () => {
		const close = vi.fn();
		const wrapper = await mountSuspended(sheet(close));

		window.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter" }));

		expect(close).not.toHaveBeenCalled();
		wrapper.unmount();
	});
});
