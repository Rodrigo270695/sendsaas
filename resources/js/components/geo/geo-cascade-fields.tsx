import { useEffect, useMemo, useRef, useState } from 'react';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';

export type GeoOption = {
    id: number;
    name: string;
};

export type GeoCascadeValue = {
    departamento_id: number | null;
    provincia_id: number | null;
    distrito_id: number | null;
};

type GeoCascadeFieldsProps = {
    departamentos: readonly GeoOption[];
    value: GeoCascadeValue;
    onChange: (next: GeoCascadeValue) => void;
    errors?: {
        departamento_id?: string;
        provincia_id?: string;
        distrito_id?: string;
    };
    disabled?: boolean;
    required?: boolean;
    labels?: {
        departamento?: string;
        provincia?: string;
        distrito?: string;
    };
    placeholders?: {
        departamento?: string;
        provincia?: string;
        distrito?: string;
    };
};

function toComboboxOptions(items: readonly GeoOption[]): ComboboxOption[] {
    return items.map((item) => ({
        value: String(item.id),
        label: item.name,
    }));
}

export function GeoCascadeFields({
    departamentos,
    value,
    onChange,
    errors,
    disabled = false,
    required = false,
    labels,
    placeholders,
}: GeoCascadeFieldsProps) {
    const [provincias, setProvincias] = useState<GeoOption[]>([]);
    const [distritos, setDistritos] = useState<GeoOption[]>([]);
    const [loadingProvincias, setLoadingProvincias] = useState(false);
    const [loadingDistritos, setLoadingDistritos] = useState(false);
    const provinciasCache = useRef(new Map<number, GeoOption[]>());
    const distritosCache = useRef(new Map<number, GeoOption[]>());

    useEffect(() => {
        const depId = value.departamento_id;

        if (depId === null) {
            setProvincias([]);
            return;
        }

        const cached = provinciasCache.current.get(depId);
        if (cached) {
            setProvincias(cached);
            return;
        }

        const controller = new AbortController();
        setLoadingProvincias(true);

        fetch(`/geo/provincias?departamento_id=${depId}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : []))
            .then((data: GeoOption[]) => {
                provinciasCache.current.set(depId, data);
                setProvincias(data);
            })
            .catch((err: { name?: string }) => {
                if (err.name !== 'AbortError') {
                    setProvincias([]);
                }
            })
            .finally(() => setLoadingProvincias(false));

        return () => controller.abort();
    }, [value.departamento_id]);

    useEffect(() => {
        const provId = value.provincia_id;

        if (provId === null) {
            setDistritos([]);
            return;
        }

        const cached = distritosCache.current.get(provId);
        if (cached) {
            setDistritos(cached);
            return;
        }

        const controller = new AbortController();
        setLoadingDistritos(true);

        fetch(`/geo/distritos?provincia_id=${provId}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : []))
            .then((data: GeoOption[]) => {
                distritosCache.current.set(provId, data);
                setDistritos(data);
            })
            .catch((err: { name?: string }) => {
                if (err.name !== 'AbortError') {
                    setDistritos([]);
                }
            })
            .finally(() => setLoadingDistritos(false));

        return () => controller.abort();
    }, [value.provincia_id]);

    const departamentoOptions = useMemo(
        () => toComboboxOptions(departamentos),
        [departamentos],
    );
    const provinciaOptions = useMemo(
        () => toComboboxOptions(provincias),
        [provincias],
    );
    const distritoOptions = useMemo(
        () => toComboboxOptions(distritos),
        [distritos],
    );

    const handleDepartamentoChange = (next: string | null) => {
        onChange({
            departamento_id: next ? Number(next) : null,
            provincia_id: null,
            distrito_id: null,
        });
    };

    const handleProvinciaChange = (next: string | null) => {
        onChange({
            ...value,
            provincia_id: next ? Number(next) : null,
            distrito_id: null,
        });
    };

    const handleDistritoChange = (next: string | null) => {
        onChange({
            ...value,
            distrito_id: next ? Number(next) : null,
        });
    };

    const compactComboboxClass = 'px-2 text-xs sm:px-3 sm:text-sm';

    return (
        <div className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-3">
            <div className="flex min-w-0 flex-col gap-1.5">
                <Label htmlFor="departamento_id" className="truncate text-sm">
                    {labels?.departamento ?? 'Departamento'}
                    {required ? (
                        <span className="text-destructive" aria-hidden>
                            {' '}
                            *
                        </span>
                    ) : null}
                </Label>
                <Combobox
                    id="departamento_id"
                    options={departamentoOptions}
                    value={
                        value.departamento_id !== null
                            ? String(value.departamento_id)
                            : null
                    }
                    onChange={handleDepartamentoChange}
                    placeholder={
                        placeholders?.departamento ?? 'Selecciona departamento'
                    }
                    searchPlaceholder="Buscar departamento..."
                    emptyMessage="Sin coincidencias."
                    disabled={disabled}
                    className={compactComboboxClass}
                    aria-invalid={Boolean(errors?.departamento_id)}
                    aria-describedby={
                        errors?.departamento_id
                            ? 'departamento_id-error'
                            : undefined
                    }
                />
                {errors?.departamento_id ? (
                    <p
                        id="departamento_id-error"
                        className="text-xs text-destructive"
                    >
                        {errors.departamento_id}
                    </p>
                ) : null}
            </div>

            <div className="flex min-w-0 flex-col gap-1.5">
                <Label htmlFor="provincia_id" className="truncate text-sm">
                    {labels?.provincia ?? 'Provincia'}
                    {required ? (
                        <span className="text-destructive" aria-hidden>
                            {' '}
                            *
                        </span>
                    ) : null}
                </Label>
                <Combobox
                    id="provincia_id"
                    options={provinciaOptions}
                    value={
                        value.provincia_id !== null
                            ? String(value.provincia_id)
                            : null
                    }
                    onChange={handleProvinciaChange}
                    placeholder={
                        value.departamento_id === null
                            ? 'Primero el departamento'
                            : (placeholders?.provincia ??
                              'Selecciona provincia')
                    }
                    searchPlaceholder="Buscar provincia..."
                    emptyMessage="Sin coincidencias."
                    disabled={disabled || value.departamento_id === null}
                    loading={loadingProvincias}
                    className={compactComboboxClass}
                    aria-invalid={Boolean(errors?.provincia_id)}
                    aria-describedby={
                        errors?.provincia_id
                            ? 'provincia_id-error'
                            : undefined
                    }
                />
                {errors?.provincia_id ? (
                    <p
                        id="provincia_id-error"
                        className="text-xs text-destructive"
                    >
                        {errors.provincia_id}
                    </p>
                ) : null}
            </div>

            <div className="flex min-w-0 flex-col gap-1.5">
                <Label htmlFor="distrito_id" className="truncate text-sm">
                    {labels?.distrito ?? 'Distrito'}
                    {required ? (
                        <span className="text-destructive" aria-hidden>
                            {' '}
                            *
                        </span>
                    ) : null}
                </Label>
                <Combobox
                    id="distrito_id"
                    options={distritoOptions}
                    value={
                        value.distrito_id !== null
                            ? String(value.distrito_id)
                            : null
                    }
                    onChange={handleDistritoChange}
                    placeholder={
                        value.provincia_id === null
                            ? 'Primero la provincia'
                            : (placeholders?.distrito ?? 'Selecciona distrito')
                    }
                    searchPlaceholder="Buscar distrito..."
                    emptyMessage="Sin coincidencias."
                    disabled={disabled || value.provincia_id === null}
                    loading={loadingDistritos}
                    className={compactComboboxClass}
                    aria-invalid={Boolean(errors?.distrito_id)}
                    aria-describedby={
                        errors?.distrito_id ? 'distrito_id-error' : undefined
                    }
                />
                {errors?.distrito_id ? (
                    <p id="distrito_id-error" className="text-xs text-destructive">
                        {errors.distrito_id}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
