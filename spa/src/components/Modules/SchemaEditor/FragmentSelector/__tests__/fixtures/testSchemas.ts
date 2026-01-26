import { JsonSchema } from "@/types";

/**
 * Simple flat schema with 3 scalar properties.
 * Used for basic selection state tests.
 */
export const simpleSchema: JsonSchema = {
	type: "object",
	title: "Person",
	properties: {
		name: { type: "string", title: "Name" },
		age: { type: "number", title: "Age" },
		active: { type: "boolean", title: "Active" }
	}
};

/**
 * Nested schema with 2 levels.
 * Root has a nested 'patient' object and a scalar 'recordId'.
 */
export const nestedSchema: JsonSchema = {
	type: "object",
	title: "MedicalRecord",
	properties: {
		recordId: { type: "string", title: "Record ID" },
		patient: {
			type: "object",
			title: "Patient",
			properties: {
				name: { type: "string", title: "Patient Name" },
				dob: { type: "string", format: "date", title: "Date of Birth" }
			}
		}
	}
};

/**
 * Schema with an array of objects.
 * Root has 'providers' array containing objects with name/npi.
 */
export const arraySchema: JsonSchema = {
	type: "object",
	title: "ProviderList",
	properties: {
		facilityName: { type: "string", title: "Facility Name" },
		providers: {
			type: "array",
			title: "Providers",
			items: {
				type: "object",
				title: "Provider",
				properties: {
					name: { type: "string", title: "Provider Name" },
					npi: { type: "string", title: "NPI Number" }
				}
			}
		}
	}
};

/**
 * Deep nested schema with 4+ levels and mixed arrays/objects.
 * Simulates complex medical records structure.
 */
export const deepSchema: JsonSchema = {
	type: "object",
	title: "HealthcareSystem",
	properties: {
		systemId: { type: "string", title: "System ID" },
		hospitals: {
			type: "array",
			title: "Hospitals",
			items: {
				type: "object",
				title: "Hospital",
				properties: {
					name: { type: "string", title: "Hospital Name" },
					address: { type: "string", title: "Address" },
					departments: {
						type: "array",
						title: "Departments",
						items: {
							type: "object",
							title: "Department",
							properties: {
								name: { type: "string", title: "Department Name" },
								floor: { type: "number", title: "Floor" },
								staff: {
									type: "array",
									title: "Staff",
									items: {
										type: "object",
										title: "Staff Member",
										properties: {
											employeeId: { type: "string", title: "Employee ID" },
											name: { type: "string", title: "Name" },
											role: { type: "string", title: "Role" }
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
};

/**
 * Empty schema with no properties.
 * Used for edge case testing.
 */
export const emptySchema: JsonSchema = {
	type: "object",
	title: "Empty"
};

/**
 * Schema with only nested models (no scalars at root).
 * Used for model-only mode testing.
 */
export const modelOnlySchema: JsonSchema = {
	type: "object",
	title: "Container",
	properties: {
		details: {
			type: "object",
			title: "Details",
			properties: {
				info: { type: "string", title: "Info" }
			}
		},
		metadata: {
			type: "object",
			title: "Metadata",
			properties: {
				version: { type: "number", title: "Version" }
			}
		}
	}
};

/**
 * Schema with multiple sibling models (Provider and Facility).
 * Used for testing deselection behavior with multiple children.
 * Structure: MedicalRecord -> Provider, Facility (both arrays)
 */
export const multipleSiblingModelsSchema: JsonSchema = {
	type: "object",
	title: "MedicalRecord",
	properties: {
		recordId: { type: "string", title: "Record ID" },
		provider: {
			type: "array",
			title: "Provider",
			items: {
				type: "object",
				title: "ProviderItem",
				properties: {
					name: { type: "string", title: "Provider Name" },
					npi: { type: "string", title: "NPI" }
				}
			}
		},
		facility: {
			type: "array",
			title: "Facility",
			items: {
				type: "object",
				title: "FacilityItem",
				properties: {
					name: { type: "string", title: "Facility Name" },
					address: { type: "string", title: "Address" }
				}
			}
		}
	}
};
