import type { JsonSchema } from "@/types";

/**
 * Comprehensive medical record schema for demo/testing purposes.
 * Used by SchemaDefinitionPlayground to demonstrate FragmentSelectorCanvas features.
 */
export const medicalRecordSchema: JsonSchema = {
	type: "object",
	title: "MedicalRecord",
	properties: {
		id: { type: "string", title: "ID" },
		createdAt: { type: "string", format: "date-time", title: "Created At" },
		isActive: { type: "boolean", title: "Is Active" },
		patient: {
			type: "object",
			title: "Patient",
			properties: {
				firstName: { type: "string", title: "First Name" },
				lastName: { type: "string", title: "Last Name" },
				dateOfBirth: { type: "string", format: "date", title: "Date of Birth" },
				age: { type: "number", title: "Age" },
				ssn: { type: "string", title: "SSN" },
				isDeceased: { type: "boolean", title: "Is Deceased" },
				address: {
					type: "object",
					title: "Address",
					properties: {
						street: { type: "string", title: "Street" },
						city: { type: "string", title: "City" },
						state: { type: "string", title: "State" },
						zip: { type: "string", title: "ZIP" },
						isVerified: { type: "boolean", title: "Is Verified" }
					}
				}
			}
		},
		providers: {
			type: "array",
			title: "Providers",
			items: {
				type: "object",
				title: "Provider",
				properties: {
					name: { type: "string", title: "Name" },
					specialty: { type: "string", title: "Specialty" },
					npi: { type: "string", title: "NPI" },
					phone: { type: "string", title: "Phone" },
					yearsExperience: { type: "number", title: "Years Experience" },
					isPrimaryCare: { type: "boolean", title: "Is Primary Care" },
					certifications: {
						type: "array",
						title: "Certifications",
						items: {
							type: "object",
							title: "Certification",
							properties: {
								name: { type: "string", title: "Name" },
								certificationNumber: { type: "string", title: "Certification Number" },
								issuedDate: { type: "string", format: "date", title: "Issued Date" },
								expirationDate: { type: "string", format: "date", title: "Expiration Date" },
								isActive: { type: "boolean", title: "Is Active" },
								issuingBody: {
									type: "object",
									title: "Issuing Body",
									properties: {
										name: { type: "string", title: "Name" },
										country: { type: "string", title: "Country" },
										website: { type: "string", title: "Website" },
										isAccredited: { type: "boolean", title: "Is Accredited" }
									}
								}
							}
						}
					},
					facilities: {
						type: "array",
						title: "Facilities",
						items: {
							type: "object",
							title: "Facility",
							properties: {
								name: { type: "string", title: "Name" },
								address: { type: "string", title: "Address" },
								phone: { type: "string", title: "Phone" },
								bedCount: { type: "number", title: "Bed Count" },
								isEmergencyCapable: { type: "boolean", title: "Is Emergency Capable" },
								departments: {
									type: "array",
									title: "Departments",
									items: {
										type: "object",
										title: "Department",
										properties: {
											name: { type: "string", title: "Name" },
											floor: { type: "number", title: "Floor" },
											extension: { type: "string", title: "Extension" },
											staffCount: { type: "number", title: "Staff Count" },
											isOpen24Hours: { type: "boolean", title: "Is Open 24 Hours" }
										}
									}
								}
							}
						}
					}
				}
			}
		},
		incidents: {
			type: "array",
			title: "Incidents",
			items: {
				type: "object",
				title: "Incident",
				properties: {
					incidentDate: { type: "string", format: "date", title: "Incident Date" },
					reportedAt: { type: "string", format: "date-time", title: "Reported At" },
					description: { type: "string", title: "Description" },
					location: { type: "string", title: "Location" },
					severityLevel: { type: "number", title: "Severity Level" },
					estimatedCost: { type: "number", title: "Estimated Cost" },
					daysLost: { type: "number", title: "Days Lost" },
					isWorkRelated: { type: "boolean", title: "Is Work Related" },
					isResolved: { type: "boolean", title: "Is Resolved" },
					requiresFollowUp: { type: "boolean", title: "Requires Follow Up" },
					notes: {
						type: "array",
						title: "Notes",
						items: {
							type: "object",
							title: "Note",
							properties: {
								author: { type: "string", title: "Author" },
								content: { type: "string", title: "Content" },
								timestamp: { type: "string", format: "date-time", title: "Timestamp" },
								isConfidential: { type: "boolean", title: "Is Confidential" }
							}
						}
					},
					diagnoses: {
						type: "array",
						title: "Diagnoses",
						items: {
							type: "object",
							title: "Diagnosis",
							properties: {
								code: { type: "string", title: "Code" },
								name: { type: "string", title: "Name" },
								diagnosedDate: { type: "string", format: "date", title: "Diagnosed Date" },
								isPrimary: { type: "boolean", title: "Is Primary" },
								isConfirmed: { type: "boolean", title: "Is Confirmed" },
								confidenceScore: { type: "number", title: "Confidence Score" }
							}
						}
					},
					treatments: {
						type: "array",
						title: "Treatments",
						items: {
							type: "object",
							title: "Treatment",
							properties: {
								name: { type: "string", title: "Name" },
								startDate: { type: "string", format: "date", title: "Start Date" },
								endDate: { type: "string", format: "date", title: "End Date" },
								dosage: { type: "string", title: "Dosage" },
								frequency: { type: "string", title: "Frequency" },
								cost: { type: "number", title: "Cost" },
								isOngoing: { type: "boolean", title: "Is Ongoing" },
								sideEffectsReported: { type: "boolean", title: "Side Effects Reported" }
							}
						}
					},
					attachments: {
						type: "array",
						title: "Attachments",
						items: {
							type: "object",
							title: "Attachment",
							properties: {
								fileName: { type: "string", title: "File Name" },
								fileType: { type: "string", title: "File Type" },
								uploadedAt: { type: "string", format: "date-time", title: "Uploaded At" },
								fileSize: { type: "number", title: "File Size (KB)" },
								isVerified: { type: "boolean", title: "Is Verified" }
							}
						}
					}
				}
			}
		},
		insurance: {
			type: "object",
			title: "Insurance",
			properties: {
				carrier: { type: "string", title: "Carrier" },
				policyNumber: { type: "string", title: "Policy Number" },
				groupNumber: { type: "string", title: "Group Number" },
				effectiveDate: { type: "string", format: "date", title: "Effective Date" },
				expirationDate: { type: "string", format: "date", title: "Expiration Date" },
				deductible: { type: "number", title: "Deductible" },
				copay: { type: "number", title: "Copay" },
				isPrimary: { type: "boolean", title: "Is Primary" },
				isActive: { type: "boolean", title: "Is Active" }
			}
		}
	}
};
