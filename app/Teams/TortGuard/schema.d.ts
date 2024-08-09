interface TeamObject {
    id: string;
    type: 'DrugInjury' | 'DrugProduct' | 'DrugGeneric' | 'Company' | 'Patent' | 'ScientificStudy' | 'Warning';
    name: string;
    description: string;
    url: string;
    meta: object;
}

interface DrugInjury extends TeamObject {
    evaluation_score: number;
    severity_level: number;
    hospitalization: boolean;
    surgical_procedure: boolean;
    permanent_disability: boolean;
    death: boolean;
    ongoing_care: boolean;
    economic_damage_min: number;
    economic_damage_max: number;
    product: DrugProduct;
    studies: ScientificStudy[];
    warnings: Warning[];
}

interface DrugProduct extends TeamObject {
    number_of_users: number;
    market_share: number;
    // The number of years the statute of limitations is tolled for this drug
    statute_of_limitations_tolling: string;
    patents: Patent[];
    // DrugProducts can have multiple DrugGenerics (ie: Actos can be pioglitazone and metformin)
    generics: DrugGeneric[];
    company: Company;
}

interface DrugGeneric extends TeamObject {
    // DrugGenerics can have multiple DrugProducts (ie: acetaminophen can be in Tylenol, Excedrin, etc)
    products: DrugProduct[];
}

interface Company extends TeamObject {
    net_income: number;
    annual_revenue: number;
    operating_income: number;
    total_assets: number;
    total_equity: number;
}

interface Patent extends TeamObject {
    patent_number: string;
    patent_filed_date: Date;
    patent_expiration_date: Date;
    patent_issued_date: Date;
}

interface ScientificStudy extends TeamObject {
    group_size: number;
    age_range: string;
    median_age: string;
    treatment_method: string;
    treatment_efficacy: string;
    complications: string;
}

interface Warning extends TeamObject {
    issued_at: Date;
    injury_risks: object | object[];
}
