import { useEffect, useRef, useState } from 'react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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
        distrito_id?: string;
    };
    disabled?: boolean;
    required?: boolean;
    labels?: {
        departamento?: string;
        provincia?: string;
        distrito?: string;
    };
};

export function GeoCascadeFields({
    departamentos,
    value,
    onChange,
    errors,
    disabled = false,
    required = false,
    labels,
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
                <Select
                    value={
                        value.departamento_id !== null
                            ? String(value.departamento_id)
                            : undefined
                    }
                    onValueChange={(next) =>
                        onChange({
                            departamento_id: next ? Number(next) : null,
                            provincia_id: null,
                            distrito_id: null,
                        })
                    }
                    disabled={disabled}
                >
                    <SelectTrigger id="departamento_id" className="w-full">
                        <SelectValue placeholder="Selecciona" />
                    </SelectTrigger>
                    <SelectContent>
                        {departamentos.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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
                <Select
                    value={
                        value.provincia_id !== null
                            ? String(value.provincia_id)
                            : undefined
                    }
                    onValueChange={(next) =>
                        onChange({
                            ...value,
                            provincia_id: next ? Number(next) : null,
                            distrito_id: null,
                        })
                    }
                    disabled={
                        disabled ||
                        value.departamento_id === null ||
                        loadingProvincias
                    }
                >
                    <SelectTrigger id="provincia_id" className="w-full">
                        <SelectValue
                            placeholder={
                                value.departamento_id === null
                                    ? 'Primero el departamento'
                                    : 'Selecciona'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {provincias.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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
                <Select
                    value={
                        value.distrito_id !== null
                            ? String(value.distrito_id)
                            : undefined
                    }
                    onValueChange={(next) =>
                        onChange({
                            ...value,
                            distrito_id: next ? Number(next) : null,
                        })
                    }
                    disabled={
                        disabled ||
                        value.provincia_id === null ||
                        loadingDistritos
                    }
                >
                    <SelectTrigger
                        id="distrito_id"
                        className="w-full"
                        aria-invalid={Boolean(errors?.distrito_id)}
                    >
                        <SelectValue
                            placeholder={
                                value.provincia_id === null
                                    ? 'Primero la provincia'
                                    : 'Selecciona'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {distritos.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors?.distrito_id ? (
                    <p className="text-xs text-destructive">
                        {errors.distrito_id}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
