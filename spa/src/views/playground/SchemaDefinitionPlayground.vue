<template>
  <div class="flex flex-col h-full">
    <!-- Controls Bar -->
    <div class="flex items-center gap-6 mb-4 p-4 bg-slate-800 rounded-lg border border-slate-600 flex-shrink-0">
      <!-- Selection Mode -->
      <div class="flex items-center gap-3">
        <span class="text-sm text-slate-400">Selection Mode:</span>
        <div class="flex gap-1">
          <button
            v-for="mode in selectionModes"
            :key="mode.value"
            class="px-3 py-1 text-xs rounded transition-colors"
            :class="selectionMode === mode.value
              ? 'bg-sky-600 text-white'
              : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
            @click="selectionMode = mode.value"
          >
            {{ mode.label }}
          </button>
        </div>
      </div>

      <!-- Type Filter -->
      <div class="flex items-center gap-3">
        <span class="text-sm text-slate-400">Type Filter:</span>
        <div class="flex gap-1">
          <button
            v-for="filter in typeFilters"
            :key="filter.value ?? 'all'"
            class="px-3 py-1 text-xs rounded transition-colors"
            :class="typeFilter === filter.value
              ? 'bg-sky-600 text-white'
              : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
            @click="typeFilter = filter.value"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content: Diagram + Sidebar -->
    <div class="flex flex-1 min-h-0 gap-4">
      <!-- Diagram Canvas -->
      <div class="flex-1 min-w-0 bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
        <FragmentSelectorCanvas
          :key="selectionMode"
          :schema="demoSchema"
          v-model="selection"
          :selection-mode="selectionMode"
          :type-filter="typeFilter"
        />
      </div>

      <!-- Selection Sidebar -->
      <div class="w-80 flex-shrink-0 flex flex-col bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
        <!-- Sidebar Header with counts -->
        <div class="flex items-center justify-between px-4 py-3 bg-slate-700 border-b border-slate-600">
          <span class="text-sm font-medium text-slate-200">Selection</span>
          <div class="flex items-center gap-3 text-xs text-slate-400">
            <span>Models: {{ modelCount }}</span>
            <span>Props: {{ propertyCount }}</span>
          </div>
        </div>

        <!-- Format Toggle -->
        <div class="flex items-center gap-2 px-4 py-2 border-b border-slate-600">
          <button
            v-for="fmt in outputFormats"
            :key="fmt"
            class="px-2 py-1 text-xs rounded transition-colors"
            :class="outputFormat === fmt
              ? 'bg-sky-600 text-white'
              : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
            @click="setOutputFormat(fmt)"
          >
            {{ fmt.toUpperCase() }}
          </button>
        </div>

        <!-- Code Viewer -->
        <div class="flex-1 min-h-0 overflow-auto">
          <CodeViewer
            :model-value="selection"
            :format="outputFormat"
            editor-class="p-3"
            hide-footer
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import FragmentSelectorCanvas from "@/components/Modules/SchemaEditor/FragmentSelector/FragmentSelectorCanvas.vue";
import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { CodeViewer, getItem, setItem } from "quasar-ui-danx";
import { computed, ref } from "vue";

// Selection state for the FragmentSelectorCanvas
const selection = ref<FragmentSelector | null>(null);

// Control options
const selectionMode = ref<"recursive" | "single-node" | "structure-only">("recursive");
const typeFilter = ref<JsonSchemaType | null>(null);

// Output format for sidebar (JSON or YAML)
const outputFormat = ref<"json" | "yaml">((getItem("fragmentSelector.outputFormat") as "json" | "yaml") ?? "json");
const outputFormats: ("json" | "yaml")[] = ["json", "yaml"];

function setOutputFormat(format: "json" | "yaml") {
  outputFormat.value = format;
  setItem("fragmentSelector.outputFormat", format);
}

const selectionModes = [
  { value: "recursive" as const, label: "Recursive" },
  { value: "single-node" as const, label: "Single Node" },
  { value: "structure-only" as const, label: "Structure Only" }
];

const typeFilters = [
  { value: null, label: "All" },
  { value: "string" as JsonSchemaType, label: "String" },
  { value: "number" as JsonSchemaType, label: "Number" },
  { value: "boolean" as JsonSchemaType, label: "Boolean" }
];

// Compute model and property counts from selection
const { modelCount, propertyCount } = (() => {
  const countSelectionItems = (selector: FragmentSelector | null): { models: number; properties: number } => {
    if (!selector) return { models: 0, properties: 0 };

    let models = 0;
    let properties = 0;

    // Count self if it's a model type
    if (selector.type === "object" || selector.type === "array") {
      models++;
    } else {
      properties++;
    }

    // Recursively count children
    if (selector.children) {
      for (const child of Object.values(selector.children)) {
        const childCounts = countSelectionItems(child);
        models += childCounts.models;
        properties += childCounts.properties;
      }
    }

    return { models, properties };
  };

  const modelCount = computed(() => countSelectionItems(selection.value).models);
  const propertyCount = computed(() => countSelectionItems(selection.value).properties);

  return { modelCount, propertyCount };
})();

// Demo schema - comprehensive medical record
const demoSchema: JsonSchema = {
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
</script>
