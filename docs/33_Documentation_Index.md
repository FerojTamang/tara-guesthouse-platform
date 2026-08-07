# Documentation Index

## Project Information

| Item | Details |
|---|---|
| Project | Tara Guest House Management System |
| Document number | 33 |
| Version | 1.3 |
| Status | Synchronized through Sprint 4 — Project Manager review required |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |
| Date | 1 August 2026 |

# 1. Purpose

This is the master inventory and reading map for the preserved 38-document project suite. Filenames and titles below match the repository. “Synchronized” means aligned to the approved Version 1 architect decisions; it does not claim that application code exists or that implementation testing has passed.

# 2. Authority and Scope

When documents conflict, follow the authority order in `00_V1_ARCHITECT_DECISIONS.md` DEC-031. Future-ready material remains useful only when clearly labeled **Future Enhancements**. Database migrations are the executable database source of truth; versioned `/api/v1/...` route contracts are authoritative for JSON endpoints.

# 3. Master Document Register

| No. | Filename | Document title | Synchronization status |
|---:|---|---|---|
| 01 | `01_Project_Overview.md` | Project Overview | Synchronized; review required |
| 02 | `02_Client_Requirements.md` | Client Requirements Specification | Synchronized; review required |
| 03 | `03_Team_Organization.md` | Team Organization & Responsibilities | Synchronized; review required |
| 04 | `04_Project_Architecture.md` | Project Architecture | Synchronized; review required |
| 05 | `05_Folder_Structure.md` | Folder Structure & Development Standards | Synchronized; review required |
| 06 | `06_Coding_Standards.md` | Coding Standards & Best Practices | Synchronized; review required |
| 07 | `07_Git_Workflow.md` | Git Workflow & Version Control | Synchronized; review required |
| 08 | `08_Database_Design.md` | Database Design | Synchronized to 27 executable migrations; development remains at 25 applied with 2 pending |
| 09 | `09_ER_Diagram.md` | Entity Relationship Diagram | Synchronized to executable Sprint 4 relationships |
| 10 | `10_Database_Dictionary.md` | Database Dictionary | Synchronized to 27 executable migrations; development remains at 25 applied with 2 pending |
| 11 | `11_Booking_System_Specification.md` | Booking System Specification | Synchronized; Sprint 2 implementation and test evidence recorded |
| 12 | `12_Room_Management_Specification.md` | Room Management Specification | Synchronized; review required |
| 13 | `13_Activity_Management_Specification.md` | Activity Management Specification | Implemented and QA synchronized in Sprint 4 |
| 14 | `14_Gallery_Management_Specification.md` | Gallery Management Specification | Implemented and QA synchronized in Sprint 4 |
| 15 | `15_Content_Management_System.md` | Content Management System Specification | Implemented and QA synchronized in Sprint 4 |
| 16 | `16_Authentication_Authorization_System.md` | Authentication & Authorization System Specification | Synchronized; review required |
| 17 | `17_Admin_Dashboard_Specification.md` | Admin Dashboard Specification | Synchronized; review required |
| 18 | `18_Review_Rating_System.md` | Review & Rating System Specification | Implemented and QA synchronized in Sprint 4 |
| 19 | `19_Payment_Management_System.md` | Payment Management System Specification | Implemented and QA synchronized in Sprint 3 |
| 20 | `20_Notification_System.md` | Notification System Specification | Administrator notifications and transactional SMTP implemented in Sprint 4 |
| 21 | `21_Business_Process_Flow.md` | Business Process Flow | Synchronized to implemented payment and invoice transactions |
| 22 | `22_System_State_Machine.md` | System State Machine | Canonical V1 model; Sprint 3 payment-driven confirmation verified |
| 23 | `23_REST_API_Specification.md` | REST API Specification | Synchronized to the 48 registered Sprint 4 JSON routes |
| 24 | `24_Frontend_Architecture.md` | Frontend Architecture | Synchronized; review required |
| 25 | `25_Backend_Architecture.md` | Backend Architecture | Synchronized; review required |
| 26 | `26_Deployment_Guide.md` | Deployment Guide | Synchronized to cPanel; host verification required |
| 27 | `27_Testing_Strategy.md` | Testing Strategy | Sprint 4 automated evidence and closure gates recorded |
| 28 | `28_User_Manual.md` | User Manual | Synchronized through Sprint 4; formal screenshots/UAT pending |
| 29 | `29_Developer_Guide.md` | Developer Guide | Synchronized through Sprint 4 |
| 30 | `30_Maintenance_Manual.md` | Maintenance Manual | Synchronized; hosting verification required |
| 31 | `31_Framework_Architecture.md` | Framework Architecture | Synchronized; review required |
| 32 | `32_Project_Implementation_Plan.md` | Project Implementation Plan | Synchronized to approved phase order |
| 33 | `33_Documentation_Index.md` | Documentation Index | Synchronized |
| 34 | `34_UI_UX_Screen_Specification.md` | UI/UX Screen Specification | Synchronized to registered Sprint 4 pages and JSON dependencies |
| 35 | `35_Requirements_Traceability_Matrix.md` | Requirements Traceability Matrix | Synchronized through Sprint 4 implementation/test traceability |
| 36 | `36_Architecture_Review_Report.md` | Architecture Review Report | Superseded readiness claims corrected |
| 37 | `37_Sprint_1_Readiness_Report.md` | Sprint 1 Readiness Report | Reassessed after Sprint 1 Phase 2 implementation and QA; ready for pull-request review |
| 38 | `38_Development_Handbook.md` | Development Handbook | Renamed and synchronized; review required |

# 4. Governance and Audit Documents

These files support, but do not alter, the preserved 38-document numbering:

| Filename | Purpose | Status |
|---|---|---|
| `00_V1_ARCHITECT_DECISIONS.md` | Authoritative Version 1 decisions | Approved |
| `DOCUMENTATION_CONSISTENCY_AUDIT.md` | Pre-synchronization findings | Historical baseline; superseded by final audit |
| `DOCUMENTATION_SYNCHRONIZATION_REPORT.md` | Synchronization change record | Created after synchronization |
| `FINAL_DOCUMENTATION_CONSISTENCY_AUDIT.md` | Post-synchronization verification | Created after synchronization |
| `39_Sprint_0_QA_Closure_Report.md` | Executed Sprint 0 foundation QA evidence and closure verdict | Approved and merged |
| `40_Sprint_1_QA_Closure_Report.md` | Executed Sprint 1 Phase 2 QA evidence and closure verdict | Ready for Technical Architect approval |
| `41_Sprint_2_QA_Closure_Report.md` | Executed Sprint 2 Availability and Booking QA evidence and closure verdict | Ready for Technical Architect approval |
| `42_Sprint_3_Payments_Invoices_Implementation_Plan.md` | Approved Sprint 3 scope, policy, tickets, and completion rule | Executed locally; closure recorded in document 43 |
| `43_Sprint_3_QA_Closure_Report.md` | Executed Sprint 3 Payments and Invoices QA evidence and closure verdict | Approved and merged |
| `44_Sprint_4_Operations_Content_Implementation_Plan.md` | Approved Sprint 4 Operations and Content scope, ticket sequence, decision gates, and incremental GitHub delivery workflow | Implementation executed locally; S4-019 closure pending |
| `45_Sprint_4_QA_Closure_Report.md` | Executed Sprint 4 Operations and Content QA evidence and closure verdict | Ready for final local closure gates |
| `46_Sprint_5_Dashboard_Hardening_Deployment_Implementation_Plan.md` | Proposed Sprint 5 dashboard, hardening, deployment, and closure sequence | Approval required; S5-001 |
| `47_Phase_6_UAT_Execution_Plan.md` | Reproducible Version 1 public/Administrator UAT scenarios, evidence rules, defect handling, and approval record | Ready for S6-007 execution |
| `archive/22_PreDecision_State_Machine_Proposals.md` | Historical pre-decision state proposals | Archived; not an implementation source |

# 5. Recommended Reading Order

1. `00_V1_ARCHITECT_DECISIONS.md`
2. `01_Project_Overview.md` and `02_Client_Requirements.md`
3. Relevant module specification from documents 11–20
4. `08_Database_Design.md`, `09_ER_Diagram.md`, `10_Database_Dictionary.md`, and the relevant migrations
5. `22_System_State_Machine.md` and `23_REST_API_Specification.md`
6. `04_Project_Architecture.md`, `24_Frontend_Architecture.md`, `25_Backend_Architecture.md`, and `31_Framework_Architecture.md`
7. `05_Folder_Structure.md`, `06_Coding_Standards.md`, `07_Git_Workflow.md`, and `38_Development_Handbook.md`
8. `27_Testing_Strategy.md`, `35_Requirements_Traceability_Matrix.md`, and the applicable ticket/acceptance criteria
9. `26_Deployment_Guide.md` and `30_Maintenance_Manual.md`

# 6. Documentation Status

The suite has been synchronized to the approved Version 1 decisions. Sprint 0 foundation is approved and merged, recorded in `39_Sprint_0_QA_Closure_Report.md`. Sprint 1 Administrator authentication and Rooms is implemented and tested in `40_Sprint_1_QA_Closure_Report.md`. Sprint 2 Availability and Booking is implemented and tested in `41_Sprint_2_QA_Closure_Report.md`. Sprint 3 Manual Payments and Invoices is implemented, merged, and post-merge regression-tested in `43_Sprint_3_QA_Closure_Report.md`. Sprint 4 Operations and Content is implemented and locally regression-tested, with closure evidence in `45_Sprint_4_QA_Closure_Report.md`. Pull-request approval/merge, post-merge verification, dashboard completion, hardening, UAT, cPanel verification, and production deployment remain later or externally gated work.

# 7. Future Documentation

Future versions may add dedicated guides for customer accounts/portal, multi-room bookings, gateway integrations, additional staff permission systems, mobile clients, SMS/WhatsApp/push, VPS/cloud/Docker infrastructure, and full CMS version history. Such documents must be approved before those capabilities enter implementation scope.
