# E-commerce Portal Brief

Use brainstorming mode first.
Ask one question at a time.
Help me define MVP vs Phase 2.
Do not jump into code until requirements are clear.

## Project summary
We are building a custom e-commerce portal for selling and delivering PDF reports.

## Tech stack
- 20i Laravel optimised cloud server
- Laravel 13
- Nginx
- MariaDB
- Tailwind CSS
- Amazon S3 for PDF storage and retrieval

## Design and UI
Should align with the website design at https://qsanalysis.co.uk

## Product model
There are three report types:
-	FAR: Financial Assessment Report, the individual charity-level report
-	PPR: Provider Portfolio Report, the named provider-level report
-	PMR: Provider Market Report, the category-level provider market report

Reports are managed by a third party and uploaded to S3 twice a year.

## Customer/account types
- Free account: access to High-level teaser, thought piece or sample output
- Paid account: access to purchased FARs, PPRs and PMRs 

## FAR pricing
### Single report
- £25.00 per FAR
- No VAT

### Report packs
| FARs | Price |
|---|---:|
| 5 | £100 |
| 10 | £150 |
| 20 | £250 |
| 50 | £450 |
| 100 | £600 |
| 200 | £800 |
| 500 | £1,250 |
| 1,000 | £1,500 |
| 2,000 | £2,000 |

### FAR subscriptions
Subscriptions start at 20 reports and include 2 issues per year.

| FARs | Price |
|---|---:|
| 20 | £375 |
| 50 | £675 |
| 100 | £900 |
| 200 | £1,200 |
| 500 | £1,875 |
| 1,000 | £2,250 |
| 2,000 | £3,000 |

## FAR report pack entitlement model
FAR report packs use a remaining-balance claim model.

After purchase, the user sees their allowance in the dashboard, for example:
- 20 of 20 reports remaining

The user can then browse FAR reports and claim them immediately. No approval is required.

For unclaimed eligible reports, the UI shows **Access Report** instead of **Buy Now**.

When the user clicks **Access Report**:
- the report title is assigned to that pack
- it becomes available in the user account
- the remaining balance decreases by 1

Once claimed, a report title is locked to that pack and cannot be swapped.

A report pack does not include future issues. It provides access only to the claimed current report titles covered by that purchase.

## FAR subscription entitlement model
A FAR subscription gives access to a fixed number of FAR report titles based on the subscription tier.

After payment, the user sees their allowance in the dashboard, for example:
- 20 of 20 reports remaining

The user can then browse FAR reports and claim them immediately. No approval is required.

For unclaimed eligible reports, the UI shows **Access Report** instead of **Buy Now**.

When the user clicks **Access Report**:
- the report title is assigned to their subscription
- it becomes available in their account
- the remaining balance decreases by 1

Once claimed, a report title is locked to that subscription and cannot be swapped.

Each claimed FAR title includes 2 issues per year.
If a subscriber claims 20 FAR titles, they receive the next issue of those same 20 titles automatically if the next issue falls within the active subscription period.

## PPRs
These reports are bought on a single report basis and locks in the current version only, no future versions.
•	Standard PPR: access to the named-provider report only
•	Enhanced PPR: access to the named-provider report plus the underlying linked charity relationship dataset
•	Premium PPR: access to the named-provider report, the underlying linked charity relationship dataset and FAR access for the charities linked to that provider, for a defined period, for example 12 months

##PMRs
These reports are bought on a single report basis and locks in the current version only, no future versions.
•	Standard PMR: access to purchased category report only
•	Premium PMR: category report plus agreed supporting data and / or defined FAR access

There would need to be version control for any PPR and PMR reports purchased

## Payments
- Stripe
- Ideally a single-step payment flow from the report detail page

## Delivery
- Reports are delivered digitally
- Purchased or claimed PDFs appear in the user account
- Access rules must follow account type and purchase/subscription status

## Key requirements to define
Please help me clarify:
1. MVP scope
2. User journeys
3. Account and permissions model
4. Catalogue and pricing model
5. Report pack entitlement logic
6. Subscription entitlement logic
7. Stripe payment flow
8. S3 file access and security model
9. Admin/report management needs
10. Reporting and analytics needs
11. Risks and future enhancements

## Likely MVP
- Browse report catalogues FARs, PPRs and PMRs
- View report detail pages
- Buy single FAR reports
- Buy FAR report packs
- Buy FAR subscriptions
- Buy PPR reports: Standard, Enhanced and Premium options
- Buy PMR reports: Standard and Premium options
- Register/login
- Free and Paid account types
- Stripe payment
- Dashboard showing remaining pack/subscription allowance
- Claim FAR reports from catalogue using Access Report
- Secure PDF delivery from S3
- Basic admin controls
- Basic customer/order/subscription management

## Likely Phase 2
- Better search/filtering
- Promotions/discount logic
- Smarter admin tools
- Marketing integrations
- Expanded customer self-service
- More advanced reporting

## Output I want from you
After brainstorming, produce:
1. a concise development plan
2. recommended architecture
3. phased roadmap
4. MVP feature list
5. open questions
6. main technical and commercial risks

Start by asking the single most important clarifying question. 