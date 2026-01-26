/**
 * Composable that manages parent chain selection for fragment selection.
 * Ensures proper hierarchy maintenance when selecting/deselecting nodes.
 */
export function useSelectionParentChain(selectionMap: Map<string, Set<string>>) {
	/**
	 * Ensure all ancestor paths have the appropriate property selected.
	 * For path "root.providers.certifications", this ensures:
	 * - "root" has "providers" selected
	 * - "root.providers" has "certifications" selected
	 */
	function ensureParentChainSelected(path: string): void {
		if (path === "root") return;

		const parts = path.split(".");
		// Start from root and work down to parent of current path
		for (let i = 1; i < parts.length; i++) {
			const parentPath = parts.slice(0, i).join(".");
			const childName = parts[i];

			if (!selectionMap.has(parentPath)) {
				selectionMap.set(parentPath, new Set());
			}
			selectionMap.get(parentPath)!.add(childName);
		}
	}

	/**
	 * Remove a node from its parent's selection set.
	 * Used when deselecting a node to ensure parent chain is updated.
	 *
	 * NOTE: This function only removes the child from the parent's selection set.
	 * It does NOT delete the parent from selectionMap even if the parent's set becomes empty.
	 * This is intentional: parent chain selection is "sticky" - once a parent is selected
	 * (via ensureParentChainSelected), it stays in the map until explicitly deselected.
	 *
	 * @param path - The path of the node to remove from parent
	 */
	function removeFromParentSelection(path: string): void {
		if (path === "root") return;

		const parts = path.split(".");
		const nodeName = parts.pop()!;
		const parentPath = parts.join(".") || "root";

		const parentSelection = selectionMap.get(parentPath);
		if (parentSelection) {
			parentSelection.delete(nodeName);
			// Note: We intentionally do NOT delete the parent when selection becomes empty.
			// The parent was explicitly added to the selection tree via ensureParentChainSelected,
			// and should remain there until explicitly deselected.
		}
	}

	/**
	 * Check if this node is selected by its parent (i.e., parent has this node's name in its selection set).
	 * This is used to determine if a model node is "included" in the fragment output.
	 */
	function isSelectedByParent(path: string): boolean {
		if (path === "root") return false;

		const parts = path.split(".");
		const nodeName = parts.pop()!;
		const parentPath = parts.join(".") || "root";

		const parentSelection = selectionMap.get(parentPath);
		return parentSelection?.has(nodeName) ?? false;
	}

	return {
		ensureParentChainSelected,
		removeFromParentSelection,
		isSelectedByParent
	};
}
